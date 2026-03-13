<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
use App\Services\RadiusRepository;

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
        $users = $this->radiusRepository->getUsers();
        $profiles = $this->radiusRepository->getProfiles();
        $this->render('users', [
            'users' => $users,
            'profiles' => $profiles,
            'title' => 'Gestão de Usuários'
        ]);
    }

    public function saveUserGroup(): void
    {
        $this->checkAuth();
        $username = $_POST['username'] ?? '';
        $groupname = $_POST['groupname'] ?? '';

        if (!empty($username) && !empty($groupname)) {
            $this->radiusRepository->setUserGroup($username, $groupname);
            $_SESSION['success'] = "Usuário {$username} associado ao grupo {$groupname}.";
        } else {
            $_SESSION['error'] = 'Dados inválidos.';
        }

        header('Location: /admin/users');
        exit;
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
}
