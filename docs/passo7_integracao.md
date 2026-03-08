# Passo 7 – Integração com o Captive Portal (Front-end/JS)

## 7.1 Estrutura do HTML
- Basear-se no template oficial do pfSense (Passo 1.3). A interface mostrará apenas um campo visível (CPF ou matrícula); os demais (`auth_pass`, `redirurl`, `zone`, `accept`) ficarão como inputs hidden preenchidos via JS.
- Adicionar elementos para mensagens de erro (`<div id="cp-error"></div>`), spinner, etc.

## 7.2 Interceptação com JavaScript
```html
<script>
async function interceptSubmit(event) {
  event.preventDefault();
  const form = event.target;
  const username = form.auth_user.value.trim().toLowerCase();
  const password = ''; // campo oculto, senha não é usada
  if (!/^[a-z0-9._-]+$/.test(username)) {
    showError('Usuário em formato inválido.');
    return;
  }
  try {
    const response = await fetch('https://endpoint-interno.local/check', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Auth-Token': '<token curto>'
      },
      body: JSON.stringify({ username, password })
    });
    const data = await response.json();
    if (data.status === 'ok') {
      form.submit();
    } else if (data.status === 'deny') {
      showError('Acesso negado.');
    } else {
      showError('Erro temporário, tente novamente.');
    }
  } catch (err) {
    showError('Falha de comunicação com o servidor interno.');
  }
}
document.querySelector('form').addEventListener('submit', interceptSubmit);
</script>
```
- `showError` deve atualizar o DOM e registrar a tentativa no backend via auditoria.
- Garantir que o JS só execute após o carregamento da página (`DOMContentLoaded`).

## 7.3 Segurança e UX
- Forçar HTTPS no endpoint interno e validar certificado.
- Token curto configurado no `.env` do backend e embutido no HTML (idealmente via template server-side, não hardcoded).
- Mensagens claras ao usuário em caso de negação ou erro.
- Se o endpoint não responder em tempo hábil (timeout), negar com mensagem "Serviço indisponível".

## 7.4 Logs no backend
- Toda decisão enviada ao front-end já estará na auditoria; opcionalmente, enviar evento adicional via fetch se precisar registrar contextos de interface.

## 7.5 Implantação
- Subir o HTML/JS no pfSense usando o upload indicado (Passo 1.3).
- Validar testes: usuário autorizado, usuário não autorizado, falha de rede.
