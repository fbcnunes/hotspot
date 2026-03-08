<?php /** @var string $token */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Conectar ao Hotspot</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body class="bg-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-[440px] bg-white rounded-[2rem] shadow-2xl overflow-hidden flex flex-col">
        <!-- Banner Area -->
        <div class="w-full aspect-[4/5] bg-slate-900 relative overflow-hidden flex items-center justify-center">
            <?php if (!empty($settings['banner_url'])): ?>
                <img src="<?= htmlspecialchars($settings['banner_url']) ?>?t=<?= time() ?>" 
                     alt="Banner" 
                     class="w-full h-full object-cover">
            <?php else: ?>
                <span class="text-slate-500 font-semibold">Hotspot Acesso</span>
            <?php endif; ?>
        </div>

        <!-- Form Area -->
        <div class="p-10">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-slate-900">Bem-vindo</h1>
                <p class="text-slate-500 text-sm mt-1">Autentique-se para navegar</p>
            </div>

            <div id="cp-error" class="alert alert-danger d-none py-3 mb-6 text-sm rounded-xl" role="alert"></div>

            <form id="cp-form" method="post" action="$PORTAL_ACTION$" class="space-y-5">
                <div>
                    <label for="auth_user" class="block text-sm font-semibold text-slate-700 mb-2">Matrícula ou CPF</label>
                    <input type="text" class="w-full px-5 py-4 rounded-2xl border-2 border-slate-200 focus:border-blue-500 focus:outline-none transition-all" 
                           id="auth_user" name="auth_user" required placeholder="Digite seus dados">
                </div>
                
                <input type="hidden" name="auth_pass" id="auth_pass" value="">
                <input type="hidden" name="auth_voucher" id="auth_voucher" value="">
                <input type="hidden" name="redirurl" value="$PORTAL_REDIRURL$">
                <input type="hidden" name="zone" value="$PORTAL_ZONE$">

                <button class="w-full bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-200 uppercase tracking-wider" 
                        type="submit" name="accept">
                    CONECTAR AGORA
                </button>
            </form>

            <p class="mt-8 text-center text-slate-400 text-xs">
                Uso restrito para usuários cadastrados.
            </p>
        </div>
    </div>

<script>
const token = <?php echo json_encode($token ?? '', JSON_UNESCAPED_UNICODE); ?>;

function showError(message) {
    const box = document.getElementById('cp-error');
    box.textContent = message;
    box.classList.remove('d-none');
}

function hideError() {
    const box = document.getElementById('cp-error');
    box.classList.add('d-none');
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cp-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideError();
        const usernameInput = document.getElementById('auth_user');
        const username = usernameInput.value.trim().toLowerCase().replace(/\s+/g, '');
        
        if (!/^[a-z0-9._-]+$/.test(username)) {
            showError('Usuário em formato inválido.');
            return;
        }

        try {
            const response = await fetch('/api/check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Auth-Token': token
                },
                body: JSON.stringify({ username })
            });
            const data = await response.json();
            if (data.status === 'ok') {
                form.submit();
            } else if (data.status === 'deny') {
                showError(data.message ?? 'Acesso negado.');
            } else {
                showError('Erro temporário. Tente novamente.');
            }
        } catch (error) {
            showError('Falha de conexão. Tente novamente.');
        }
    });
});
</script>
</body>
</html>
