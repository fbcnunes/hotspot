<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use App\Utils\Normalizer;
use RuntimeException;

final class AuthService
{
    public function __construct(
        private SqlServerRepository $sqlServerRepository,
        private RadiusRepository $radiusRepository,
        private AuditService $auditService
    ) {
    }

    /**
     * @return array{status:string,message?:string}
     */
    public function process(string $username, string $requestId, string $originIp): array
    {
        $normalized = Normalizer::normalizeUsername($username);
        if ($normalized === '' || !Normalizer::isValidUsername($normalized)) {
            $this->auditService->record($normalized, 'negado', $originIp, 'username_invalido', $requestId);
            return ['status' => 'deny', 'message' => 'Usuário inválido'];
        }

        if ($this->radiusRepository->isUserAuthBlocked($normalized)) {
            $this->auditService->record($normalized, 'negado', $originIp, 'usuario_bloqueado', $requestId);
            return [
                'status' => 'deny',
                'message' => 'Usuário com erro. Procure o suporte para liberação.',
            ];
        }

        $match = $this->sqlServerRepository->findByLogin($normalized);
        if (!$match) {
            $this->auditService->record($normalized, 'negado', $originIp, 'usuario_nao_autorizado', $requestId);
            return ['status' => 'deny', 'message' => 'Usuário não autorizado'];
        }

        $password = Config::get('FIXED_PASSWORD_CLEARTEXT');
        if (!$password) {
            throw new RuntimeException('FIXED_PASSWORD_CLEARTEXT não configurado.');
        }

        $this->radiusRepository->upsertCleartextPassword($normalized, (string)$password);
        $this->radiusRepository->syncUserMetadata($normalized, $match);
        $this->auditService->record($normalized, 'permitido', $originIp, 'upsert_ok', $requestId);

        return ['status' => 'ok'];
    }
}
