<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;

final class PortalController
{
    private \App\Services\RadiusRepository $radiusRepository;

    public function __construct(\App\Services\RadiusRepository $radiusRepository)
    {
        $this->radiusRepository = $radiusRepository;
    }

    public function index(): void
    {
        $token = Config::get('PORTAL_TOKEN', '');
        $settings = $this->radiusRepository->getSettings();
        $viewPath = __DIR__ . '/../Views/portal.php';
        $data = [
            'token' => $token,
            'settings' => $settings,
        ];
        extract($data);
        require $viewPath;
    }
}
