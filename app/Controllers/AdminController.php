<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Services\AuditService;
use App\Services\RadiusRepository;
use App\Utils\Normalizer;

final class AdminController
{
    private const BANNER_FILENAME = 'banner.png';
    private const BANNER_ROUTE = '/banner.png';

    private RadiusRepository $radiusRepository;

    public function __construct(RadiusRepository $radiusRepository)
    {
        $this->radiusRepository = $radiusRepository;
    }

    public function index(): void
    {
        $this->checkAuth();
        $sessions = $this->radiusRepository->getActiveSessions();
        $consumption = $this->radiusRepository->getConsumptionHistory();
        $this->render('dashboard', [
            'sessions' => $sessions,
            'consumption' => $consumption,
            'title' => 'Dashboard'
        ]);
    }

    public function login(): void
    {
        if (isset($_SESSION['admin_logged_in'])) {
            header('Location: /admin');
            exit;
        }
        $this->render('login', ['title' => 'Login Administrativo']);
    }

    public function auth(): void
    {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        $adminUser = Config::get('ADMIN_USERNAME');
        $adminPass = Config::get('ADMIN_PASSWORD');

        if ($user === $adminUser && $pass === $adminPass) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: /admin');
            exit;
        }

        $_SESSION['error'] = 'Credenciais inválidas.';
        header('Location: /admin/login');
        exit;
    }

    public function logout(): void
    {
        session_destroy();
        header('Location: /admin/login');
        exit;
    }

    public function profiles(): void
    {
        $this->checkAuth();
        $profiles = $this->radiusRepository->getProfiles();
        $this->render('profiles', [
            'profiles' => $profiles,
            'title' => 'Perfis de Banda'
        ]);
    }

    public function saveProfile(): void
    {
        $this->checkAuth();
        $data = [
            'name' => $_POST['name'] ?? '',
            'download_kbps' => (int)(($_POST['download_mbps'] ?? 0) * 1024),
            'upload_kbps' => (int)(($_POST['upload_mbps'] ?? 0) * 1024),
            'session_timeout_seconds' => (int)(($_POST['session_timeout_minutes'] ?? 0) * 60),
            'idle_timeout_seconds' => (int)(($_POST['idle_timeout_minutes'] ?? 0) * 60),
            'redir_url' => $_POST['redir_url'] ?? '',
        ];

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Nome do perfil é obrigatório.';
        } else {
            $this->radiusRepository->saveProfile($data);
            $_SESSION['success'] = 'Perfil salvo com sucesso.';
        }

        header('Location: /admin/profiles');
        exit;
    }

    public function settings(): void
    {
        $this->checkAuth();
        $settings = $this->radiusRepository->getSettings();
        $this->render('settings', [
            'settings' => $settings,
            'title' => 'Configurações'
        ]);
    }

    public function saveSettings(): void
    {
        $this->checkAuth();
        error_log("[hotspot] AdminController: saveSettings() hit");
        $bannerUrl = $_POST['banner_url'] ?? '';
        $redirectUrl = $_POST['global_redir_url'] ?? '';

        $this->radiusRepository->updateSetting('banner_url', $bannerUrl);
        $this->radiusRepository->updateSetting('global_redir_url', $redirectUrl);

        $_SESSION['success'] = 'Configurações salvas!';
        header('Location: /admin/settings');
        exit;
    }

    public function uploadBanner(): void
    {
        $this->checkAuth();
        error_log("[hotspot] AdminController: uploadBanner() hit");
        
        if (!isset($_FILES['banner_file'])) {
            $_SESSION['error'] = 'Nenhum arquivo enviado.';
            header('Location: /admin/settings');
            exit;
        }

        $uploadError = $_FILES['banner_file']['error'];
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errorMsg = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite do servidor (php.ini).',
                UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite do formulário.',
                UPLOAD_ERR_PARTIAL => 'O upload foi interrompido.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo selecionado.',
                default => 'Erro interno no upload: ' . $uploadError
            };
            $_SESSION['error'] = $errorMsg;
            header('Location: /admin/settings');
            exit;
        }

        $tmpPath = $_FILES['banner_file']['tmp_name'];

        try {
            $destPath = $this->resolveWritableBannerPath();
            $imageService = new \App\Services\ImageService();
            $imageService->processTo9by16($tmpPath, $destPath, 1080);

            $localUrl = $this->buildBannerUrl();
            $this->radiusRepository->updateSetting('banner_url', $localUrl);
            $_SESSION['success'] = 'Banner enviado e processado com sucesso (1080x1920)!';
        } catch (\Exception $e) {
            error_log('[hotspot] Image processing failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Erro ao processar imagem: ' . $e->getMessage();
        }

        header('Location: /admin/settings');
        exit;
    }

    public function banner(): void
    {
        $bannerPath = $this->resolveExistingBannerPath();
        if ($bannerPath === null || !is_file($bannerPath)) {
            http_response_code(404);
            echo 'Banner não encontrado.';
            return;
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . (string) filesize($bannerPath));
        header('Cache-Control: public, max-age=300');
        readfile($bannerPath);
    }

    public function logs(): void
    {
        $this->checkAuth();
        $logDir = __DIR__ . '/../../storage/logs';
        $files = is_dir($logDir) ? array_diff(scandir($logDir), ['.', '..']) : [];
        $this->render('logs', [
            'files' => $files,
            'title' => 'Logs do Sistema'
        ]);
    }

    public function viewLog(): void
    {
        $this->checkAuth();
        $file = $_GET['file'] ?? '';
        $logDir = realpath(__DIR__ . '/../../storage/logs');
        $filePath = realpath($logDir . '/' . $file);

        if ($filePath && str_starts_with($filePath, $logDir) && file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $this->render('view_log', [
                'content' => $content,
                'filename' => $file,
                'title' => 'Visualizando Log: ' . $file
            ]);
        } else {
            $_SESSION['error'] = 'Log não encontrado.';
            header('Location: /admin/logs');
            exit;
        }
    }

    public function users(): void
    {
        $this->checkAuth();
        $filters = [
            'matricula' => trim((string) ($_GET['matricula'] ?? '')),
            'name' => trim((string) ($_GET['name'] ?? '')),
            'cpf' => trim((string) ($_GET['cpf'] ?? '')),
            'groupname' => trim((string) ($_GET['groupname'] ?? '')),
            'blocked_status' => $this->normalizeFilterValue(
                (string) ($_GET['blocked_status'] ?? 'all'),
                ['all', 'blocked', 'unblocked'],
                'all'
            ),
            'session_status' => $this->normalizeFilterValue(
                (string) ($_GET['session_status'] ?? 'all'),
                ['all', 'active', 'inactive'],
                'all'
            ),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $userSearch = $this->radiusRepository->searchUsers($filters, $page, 50);
        $profiles = $this->radiusRepository->getProfiles();

        $this->render('users', [
            'users' => $userSearch['data'],
            'userFilters' => $filters,
            'hasActiveFilters' => $this->hasActiveUserFilters($filters),
            'pagination' => [
                'page' => $userSearch['page'],
                'per_page' => $userSearch['per_page'],
                'total' => $userSearch['total'],
                'pages' => $userSearch['pages'],
            ],
            'profiles' => $profiles,
            'title' => 'Gestão de Usuários'
        ]);
    }

    public function saveUserGroup(): void
    {
        $this->checkAuth();
        $username = $_POST['username'] ?? '';
        $groupname = $_POST['groupname'] ?? '';
        $returnQuery = trim((string) ($_POST['return_query'] ?? ''));

        if (!empty($username) && !empty($groupname)) {
            $this->radiusRepository->setUserGroup($username, $groupname);
            $_SESSION['success'] = "Usuário {$username} associado ao grupo {$groupname}.";
        } else {
            $_SESSION['error'] = 'Dados inválidos.';
        }

        $redirect = '/admin/users';
        if ($returnQuery !== '') {
            $redirect .= '?' . ltrim($returnQuery, '?');
        }

        header('Location: ' . $redirect);
        exit;
    }

    public function disconnectUser(): void
    {
        $this->checkAuth();
        $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
        $username = $this->resolveAndValidateUsername((string) ($_POST['username'] ?? ''));
        $originIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestId = $this->generateRequestId();

        if ($username === null) {
            $_SESSION['error'] = 'Usuário inválido para desautenticar.';
            $this->redirectUsers($returnQuery);
        }

        try {
            $result = $this->radiusRepository->disconnectUser($username);
            $message = $this->buildDisconnectMessage($username, $result);
            $_SESSION['success'] = $message;

            $audit = new AuditService();
            $audit->record(
                $username,
                'admin_disconnect',
                $originIp,
                $message,
                $requestId
            );
        } catch (\Throwable $e) {
            error_log(sprintf('[hotspot] disconnectUser() failed for "%s": %s', $username, $e->getMessage()));
            $_SESSION['error'] = 'Falha ao desautenticar usuário.';
            try {
                $audit = new AuditService();
                $audit->record(
                    $username,
                    'admin_disconnect_error',
                    $originIp,
                    'exception: ' . substr($e->getMessage(), 0, 120),
                    $requestId
                );
            } catch (\Throwable $auditError) {
                error_log('[hotspot] Failed to record disconnect audit: ' . $auditError->getMessage());
            }
        }

        $this->redirectUsers($returnQuery);
    }

    public function userSessions(): void
    {
        $this->checkAuth();
        $username = $this->resolveAndValidateUsername((string) ($_GET['username'] ?? ''));

        if ($username === null) {
            $this->json([
                'status' => 'error',
                'message' => 'Usuário inválido.',
            ], 422);
            return;
        }

        try {
            $sessions = $this->radiusRepository->getActiveSessionsByUsername($username);
            $this->json([
                'status' => 'ok',
                'sessions' => $sessions,
            ], 200);
        } catch (\Throwable $e) {
            error_log(sprintf('[hotspot] userSessions() failed for "%s": %s', $username, $e->getMessage()));
            $this->json([
                'status' => 'error',
                'message' => 'Falha ao consultar sessões.',
            ], 500);
        }
    }

    public function disconnectSession(): void
    {
        $this->checkAuth();
        $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
        $radacctId = (int) ($_POST['radacctid'] ?? 0);
        $originIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestId = $this->generateRequestId();

        if ($radacctId <= 0) {
            $_SESSION['error'] = 'Sessão inválida para desconexão.';
            $this->redirectUsers($returnQuery);
        }

        try {
            $result = $this->radiusRepository->disconnectSessionByRadacctId($radacctId);
            if (($result['already_closed'] ?? false) === true) {
                $message = "Sessão {$radacctId} já estava encerrada.";
            } elseif (($result['success'] ?? false) === true) {
                $message = "Sessão {$radacctId} desconectada com sucesso.";
            } else {
                $message = "Não foi possível desconectar a sessão {$radacctId} imediatamente.";
            }

            $_SESSION['success'] = $message;
            $audit = new AuditService();
            $audit->record(
                (string) $radacctId,
                'admin_session_disconnect',
                $originIp,
                $message,
                $requestId
            );
        } catch (\Throwable $e) {
            error_log(sprintf('[hotspot] disconnectSession() failed for "%d": %s', $radacctId, $e->getMessage()));
            $_SESSION['error'] = 'Falha ao desconectar sessão.';
            try {
                $audit = new AuditService();
                $audit->record(
                    (string) $radacctId,
                    'admin_session_disconnect_error',
                    $originIp,
                    'exception: ' . substr($e->getMessage(), 0, 120),
                    $requestId
                );
            } catch (\Throwable $auditError) {
                error_log('[hotspot] Failed to record session disconnect audit: ' . $auditError->getMessage());
            }
        }

        $this->redirectUsers($returnQuery);
    }

    public function unblockUser(): void
    {
        $this->checkAuth();
        $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
        $username = $this->resolveAndValidateUsername((string) ($_POST['username'] ?? ''));
        $originIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $requestId = $this->generateRequestId();

        if ($username === null) {
            $_SESSION['error'] = 'Usuário inválido para desbloquear.';
            $this->redirectUsers($returnQuery);
        }

        try {
            $this->radiusRepository->unblockUserAuth($username);
            $message = "Bloqueio removido para {$username}. Nova autenticação permitida.";
            $_SESSION['success'] = $message;

            $audit = new AuditService();
            $audit->record(
                $username,
                'admin_unblock',
                $originIp,
                $message,
                $requestId
            );
        } catch (\Throwable $e) {
            error_log(sprintf('[hotspot] unblockUser() failed for "%s": %s', $username, $e->getMessage()));
            $_SESSION['error'] = 'Falha ao remover bloqueio do usuário.';
            try {
                $audit = new AuditService();
                $audit->record(
                    $username,
                    'admin_unblock_error',
                    $originIp,
                    'exception: ' . substr($e->getMessage(), 0, 120),
                    $requestId
                );
            } catch (\Throwable $auditError) {
                error_log('[hotspot] Failed to record unblock audit: ' . $auditError->getMessage());
            }
        }

        $this->redirectUsers($returnQuery);
    }

    private function checkAuth(): void
    {
        if (!isset($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . "/../Views/admin/{$view}.php";
        
        // Incluir layout base
        require __DIR__ . '/../Views/admin/layout/header.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo "View [{$view}] não encontrada.";
        }
        require __DIR__ . '/../Views/admin/layout/footer.php';
    }

    private function buildBannerUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '192.168.121.10';

        return "{$protocol}://{$host}" . self::BANNER_ROUTE;
    }

    private function resolveWritableBannerPath(): string
    {
        foreach ($this->bannerStorageDirectories() as $directory) {
            if ($this->ensureWritableDirectory($directory)) {
                return $directory . '/' . self::BANNER_FILENAME;
            }
        }

        throw new \RuntimeException(
            'Nenhum diretório gravável foi encontrado para salvar o banner. Verifique as permissões de "storage/" ou do diretório temporário do PHP.'
        );
    }

    private function resolveExistingBannerPath(): ?string
    {
        foreach ($this->bannerStorageDirectories() as $directory) {
            $path = $directory . '/' . self::BANNER_FILENAME;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function bannerStorageDirectories(): array
    {
        return [
            __DIR__ . '/../../storage/banners',
            rtrim(sys_get_temp_dir(), '/\\') . '/hotspot',
        ];
    }

    private function ensureWritableDirectory(string $directory): bool
    {
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        return is_writable($directory);
    }

    private function hasActiveUserFilters(array $filters): bool
    {
        foreach (['matricula', 'name', 'cpf', 'groupname'] as $key) {
            $value = $filters[$key] ?? '';
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        if (($filters['blocked_status'] ?? 'all') !== 'all') {
            return true;
        }

        if (($filters['session_status'] ?? 'all') !== 'all') {
            return true;
        }

        return false;
    }

    private function resolveAndValidateUsername(string $username): ?string
    {
        $normalized = Normalizer::normalizeUsername($username);
        if ($normalized === '' || !Normalizer::isValidUsername($normalized)) {
            return null;
        }

        return $normalized;
    }

    private function redirectUsers(string $returnQuery): void
    {
        $redirect = '/admin/users';
        if ($returnQuery !== '') {
            $redirect .= '?' . ltrim($returnQuery, '?');
        }

        header('Location: ' . $redirect);
        exit;
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function buildDisconnectMessage(string $username, array $result): string
    {
        if (!($result['blocked'] ?? false)) {
            return "Usuário {$username}: não foi possível aplicar bloqueio.";
        }

        if (($result['has_active_session'] ?? false) && ($result['disconnect_success'] ?? false)) {
            $activeSessionCount = (int) ($result['active_session_count'] ?? 0);
            if ($activeSessionCount > 1) {
                return "Usuário {$username} bloqueado e {$activeSessionCount} sessões ativas foram desconectadas.";
            }

            return "Usuário {$username} desautenticado e bloqueado com sucesso.";
        }

        if (($result['has_active_session'] ?? false)) {
            $activeSessionCount = (int) ($result['active_session_count'] ?? 0);
            $successCount = (int) ($result['disconnect_success_count'] ?? 0);
            if ($activeSessionCount > 1 && $successCount > 0) {
                return "Usuário {$username} bloqueado. {$successCount} de {$activeSessionCount} sessões foram desconectadas; as demais cairão na próxima reautenticação.";
            }

            return "Usuário {$username} bloqueado. A sessão ativa será encerrada na próxima reautenticação.";
        }

        return "Usuário {$username} bloqueado. Próximo login será negado até desbloqueio.";
    }

    /**
     * @param list<string> $allowed
     */
    private function normalizeFilterValue(string $value, array $allowed, string $default): string
    {
        $value = trim($value);
        return in_array($value, $allowed, true) ? $value : $default;
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
}
