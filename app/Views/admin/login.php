<?php if (!isset($_SESSION['admin_logged_in'])): ?>
<div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-slate-900 mb-2">Acesso Admin</h1>
            <p class="text-slate-500">Área restrita para gerenciamento do Hotspot</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-4 p-3 bg-rose-100 text-rose-700 rounded text-sm text-center">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="/admin/auth" method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Usuário</label>
                <input type="text" name="username" required 
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Senha</label>
                <input type="password" name="password" required 
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
            </div>
            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition shadow-md">
                Entrar no Painel
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
