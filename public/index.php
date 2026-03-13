<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\PortalController;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\RadiusRepository;
use App\Services\SqlServerRepository;

require_once __DIR__ . '/../bootstrap/app.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$radiusRepo = new RadiusRepository();
$portalController = new PortalController($radiusRepo);
$apiController = new ApiController(
    new AuthService(
        new SqlServerRepository(),
        $radiusRepo,
        new AuditService()
    ),
    $radiusRepo
);
$adminController = new AdminController($radiusRepo);

return match (true) {
    $path === '/' || $path === '/hotspot' || $path === '/hotspot/' => $portalController->index(),
    $path === '/api/check' => $apiController->check(),
    $path === '/api/settings' => $apiController->settings(),
    $path === '/healthz' => $apiController->health(),
    $path === '/admin' => $adminController->index(),
    $path === '/admin/login' => $adminController->login(),
    $path === '/admin/auth' => $adminController->auth(),
    $path === '/admin/logout' => $adminController->logout(),
    $path === '/admin/profiles' && $_SERVER['REQUEST_METHOD'] === 'GET' => $adminController->profiles(),
    $path === '/admin/profiles' && $_SERVER['REQUEST_METHOD'] === 'POST' => $adminController->saveProfile(),
    $path === '/admin/settings' && $_SERVER['REQUEST_METHOD'] === 'GET' => $adminController->settings(),
    $path === '/admin/settings' && $_SERVER['REQUEST_METHOD'] === 'POST' => $adminController->saveSettings(),
    $path === '/admin/settings/upload-banner' && $_SERVER['REQUEST_METHOD'] === 'POST' => $adminController->uploadBanner(),
    $path === '/banner.png' && $_SERVER['REQUEST_METHOD'] === 'GET' => $adminController->banner(),
    $path === '/admin/logs' => $adminController->logs(),
    $path === '/admin/logs/view' => $adminController->viewLog(),
    $path === '/admin/users' => $adminController->users(),
    $path === '/admin/users/save-group' => $adminController->saveUserGroup(),
    default => notFound(),
};

function notFound(): void
{
    http_response_code(404);
    echo 'Not Found';
}
