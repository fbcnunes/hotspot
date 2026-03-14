<?php
/**
 * pfSense Captive Portal - Immersive Layout Version
 * Este arquivo deve ser carregado no Gerenciador de Arquivos do pfSense (ou usado como index.php)
 */
$apiUrl = 'http://10.10.0.51/api/check';
$token = 'change-me'; // O mesmo token configurado no .env do servidor
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Conectar ao Hotspot</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --glass-bg: rgba(255, 255, 255, 0.95);
        }
        * {
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        body, html {
            min-height: 100%;
            margin: 0;
            background:
                radial-gradient(circle at top, #dbeafe 0%, #f8fafc 38%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .main-card {
            background: #ffffff;
            width: 100%;
            max-width: 980px;
            border-radius: 2rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
            display: flex;
            flex-direction: column;
            min-height: min(92vh, 820px);
        }
        .banner-container {
            width: 100%;
            aspect-ratio: 9 / 16;
            background: #0f172a;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 320px;
        }
        #banner-img-element {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: none;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
        }
        .banner-placeholder {
            color: #cbd5e1;
            font-weight: 600;
            text-align: center;
            padding: 1rem;
        }
        .form-area {
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .h3 { margin: 0; font-size: 1.5rem; font-weight: 700; color: #0f172a; text-align: center; }
        .subtitle { font-size: 0.875rem; color: #64748b; margin-top: 4px; text-align: center; margin-bottom: 2rem; }
        
        .alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        .d-none { display: none !important; }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            border-radius: 1rem;
            border: 2px solid #e2e8f0;
            padding: 14px 20px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 1rem;
            padding: 16px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1rem;
            transition: all 0.2s;
        }
        .btn-primary:active { transform: scale(0.98); }
        
        .footer-note {
            margin-top: 1.5rem;
            color: #94a3b8;
            font-size: 0.75rem;
            text-align: center;
        }
        @media (min-width: 860px) {
            .main-card {
                flex-direction: row;
            }
            .banner-container {
                width: min(42%, 360px);
                min-height: auto;
                aspect-ratio: auto;
            }
            .form-area {
                flex: 1;
                padding: 3rem;
            }
        }
        @media (max-width: 480px) {
            body, html {
                padding: 10px;
            }
            .main-card {
                border-radius: 1.5rem;
                min-height: auto;
            }
            .banner-container {
                min-height: 260px;
            }
            .form-area {
                padding: 1.5rem 1.25rem 1.75rem;
            }
            .btn-primary {
                padding: 14px;
            }
        }
        @media (max-width: 859px) {
            #banner-img-element {
                object-fit: contain;
            }
        }
    </style>
</head>
<body>
    <div class="main-card">
        <div class="banner-container" id="banner-wrapper">
            <span class="banner-placeholder">Carregando banner...</span>
            <img id="banner-img-element" src="" alt="Banner">
        </div>

        <div class="form-area">
            <div class="mb-4">
                <h1 class="h3">Bem-vindo</h1>
                <p class="subtitle">Autentique-se para navegar</p>
            </div>

            <div id="cp-error" class="alert d-none"></div>

            <form id="cp-form" method="post" action="$PORTAL_ACTION$" class="space-y-4">
                <div class="mb-3">
                    <label for="auth_user" class="form-label">Matrícula ou CPF</label>
                    <input type="text" class="form-control" id="auth_user" name="auth_user" required placeholder="Digite seus dados">
                </div>
                
                <input type="hidden" name="auth_pass" id="auth_pass" value="change-me">
                <input type="hidden" name="auth_voucher" id="auth_voucher" value="">
                <input type="hidden" name="redirurl" value="$PORTAL_REDIRURL$">
                <input type="hidden" name="zone" value="$PORTAL_ZONE$">
                <input type="hidden" name="accept" value="Entrar">

                <button class="btn-primary" type="submit">CONECTAR AGORA</button>
            </form>

            <p class="footer-note">
                Uso restrito para usuários cadastrados.
            </p>
        </div>
    </div>

<script>
const API_URL = <?php echo json_encode($apiUrl, JSON_UNESCAPED_SLASHES); ?>;
const TOKEN = <?php echo json_encode($token, JSON_UNESCAPED_UNICODE); ?>;

function showError(message) {
    const box = document.getElementById('cp-error');
    box.textContent = message;
    box.classList.remove('d-none');
}

function hideError() {
    const box = document.getElementById('cp-error');
    box.classList.add('d-none');
}

async function loadSettings() {
    console.log('loadSettings: Chamando API...', API_URL.replace('/check', '/settings'));
    try {
        const response = await fetch(API_URL.replace('/check', '/settings'), {
            headers: { 'X-Auth-Token': TOKEN }
        });
        const data = await response.json();
        if (data.status === 'ok' && data.banner_url) {
            const img = document.getElementById('banner-img-element');
            const placeholder = document.querySelector('.banner-placeholder');
            const separator = data.banner_url.includes('?') ? '&' : '?';
            
            img.onload = () => {
                img.style.display = 'block';
                if(placeholder) placeholder.style.display = 'none';
            };
            
            img.src = data.banner_url + separator + 't=' + new Date().getTime();
        } else {
            console.warn('loadSettings: Sem banner, mantendo placeholder.');
        }
    } catch (err) {
        console.error('loadSettings: Falha crítica:', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadSettings();
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
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Auth-Token': TOKEN
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
            showError('Falha de conexão com o servidor.');
        }
    });
});
</script>
</body>
</html>
