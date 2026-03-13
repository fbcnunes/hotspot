<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Services\AuthService;
use App\Utils\Normalizer;
final class ApiController
{
    public function __construct(
        private AuthService $authService,
        private \App\Services\RadiusRepository $radiusRepository
    ) {
    }

    public function settings(): void
    {
        $this->applyCors();
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $this->assertToken();
        
        $settings = $this->radiusRepository->getSettings();
        $this->json([
            'status' => 'ok',
            'banner_url' => $settings['banner_url'] ?? '',
        ], 200);
    }

    public function check(): void
    {
        $this->applyCors();
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $this->assertAllowedIp();
        $this->assertToken();

        $payload = json_decode((string)file_get_contents('php://input'), true);
        $username = is_array($payload) ? ($payload['username'] ?? '') : '';

        $requestId = $this->generateRequestId();
        $originIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $result = $this->authService->process($username, $requestId, $originIp);
            $this->json($result, $result['status'] === 'ok' ? 200 : 403);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            error_log(sprintf('[hotspot] check() error for user "%s": %s', $username, $errorMessage));
            
            // Record failure in audit log if possible
            try {
                $auditService = new \App\Services\AuditService();
                $auditService->record(
                    \App\Utils\Normalizer::normalizeUsername($username),
                    'erro_sistema',
                    $originIp,
                    'exception: ' . substr($errorMessage, 0, 100),
                    $requestId
                );
            } catch (\Throwable $auditError) {
                error_log('[hotspot] Failed to record audit for error: ' . $auditError->getMessage());
            }

            $this->json(['status' => 'deny', 'message' => 'Serviço temporariamente indisponível. Tente novamente.'], 503);
        }
    }

    public function health(): void
    {
        $this->applyCors();
        $status = [
            'status' => 'ok',
            'time' => date(DATE_ATOM),
        ];
        $this->json($status, 200);
    }

    private function assertAllowedIp(): void
    {
        $allowed = Config::get('ALLOWED_IP');
        $originIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($allowed && $originIp !== $allowed) {
            $this->json(['status' => 'deny', 'message' => 'Origem não autorizada'], 403);
            exit;
        }
    }

    private function assertToken(): void
    {
        $provided = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        $expected = Config::get('PORTAL_TOKEN', '');
        if ($expected && !hash_equals($expected, $provided)) {
            $this->json(['status' => 'deny', 'message' => 'Token inválido'], 403);
            exit;
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    private function json(array $data, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function applyCors(): void
    {
        $configuredOrigins = trim((string)(Config::get('CORS_ALLOW_ORIGINS', '*') ?? '*'));
        if ($configuredOrigins === '' || strcasecmp($configuredOrigins, 'none') === 0) {
            return;
        }

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $originToSend = $this->resolveAllowedOrigin($configuredOrigins, $requestOrigin);
        if ($originToSend !== '') {
            header('Access-Control-Allow-Origin: ' . $originToSend, true);
            if ($originToSend !== '*') {
                header('Vary: Origin', true);
            }
        }

        header('Access-Control-Allow-Headers: ' . $this->resolveAllowedHeaders(), true);
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS', true);
        header('Access-Control-Max-Age: 600', true);

        if ($this->isPrivateNetworkPreflight()) {
            header('Access-Control-Allow-Private-Network: true', true);
        }
    }

    private function resolveAllowedOrigin(string $configuredOrigins, string $requestOrigin): string
    {
        if ($configuredOrigins === '*') {
            return '*';
        }

        $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))));
        if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
            return $requestOrigin;
        }

        return $allowedOrigins[0] ?? '';
    }

    private function resolveAllowedHeaders(): string
    {
        $requestedHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
        if ($requestedHeaders !== '') {
            return $requestedHeaders;
        }

        return 'Content-Type, X-Auth-Token';
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function isPrivateNetworkPreflight(): bool
    {
        $requestedPrivateNetwork = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '';
        return strcasecmp($requestedPrivateNetwork, 'true') === 0;
    }
}
