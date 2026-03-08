<?php
require_once __DIR__ . '/bootstrap/app.php';
$repo = new \App\Services\RadiusRepository();
$settings = $repo->getSettings();
print_r($settings);
