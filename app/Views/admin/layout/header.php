<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Hotspot – <?= $title ?? 'Admin' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; width: 260px; }
        .nav-link:hover { background-color: rgba(255,255,255,0.1); }
        .nav-link.active { background-color: rgba(255,255,255,0.2); border-left: 4px solid #fff; }
    </style>
</head>
<body class="bg-slate-50">
<?php if (isset($_SESSION['admin_logged_in'])): ?>
<div class="flex">
    <!-- Sidebar -->
    <aside class="sidebar bg-slate-900 text-white flex flex-col fixed left-0 top-0 h-full">
        <div class="p-6 text-2xl font-bold border-b border-slate-800">
            Hotspot <span class="text-blue-400">Admin</span>
        </div>
        <nav class="flex-1 py-4">
            <a href="/admin" class="nav-link flex items-center px-6 py-3 transition <?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/admin' ? 'active' : '' ?>">
                <i class="fas fa-chart-line w-6"></i> Dashboard
            </a>
            <a href="/admin/users" class="nav-link flex items-center px-6 py-3 transition <?= strpos($_SERVER['REQUEST_URI'], '/admin/users') !== false ? 'active' : '' ?>">
                <i class="fas fa-user-friends w-6"></i> Usuários
            </a>
            <a href="/admin/profiles" class="nav-link flex items-center px-6 py-3 transition <?= strpos($_SERVER['REQUEST_URI'], '/admin/profiles') !== false ? 'active' : '' ?>">
                <i class="fas fa-users-cog w-6"></i> Perfis de Banda
            </a>
            <a href="/admin/settings" class="nav-link flex items-center px-6 py-3 transition <?= strpos($_SERVER['REQUEST_URI'], '/admin/settings') === 0 ? 'active' : '' ?>">
                <i class="fas fa-cogs w-6"></i> Configurações
            </a>
            <a href="/admin/logs" class="nav-link flex items-center px-6 py-3 transition <?= strpos($_SERVER['REQUEST_URI'], '/admin/logs') === 0 ? 'active' : '' ?>">
                <i class="fas fa-file-waveform w-6"></i> Logs do Sistema
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="/admin/logout" class="flex items-center text-slate-400 hover:text-white transition">
                <i class="fas fa-sign-out-alt w-6"></i> Sair
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 ml-[260px] p-8">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-emerald-100 text-emerald-800 border-l-4 border-emerald-500 rounded shadow-sm">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 p-4 bg-rose-100 text-rose-800 border-l-4 border-rose-500 rounded shadow-sm">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
<?php endif; ?>
