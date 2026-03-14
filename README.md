# Hotspot Captive Portal Backend

Implementação PHP em arquitetura MVC simples para atuar como pré-validador do captive portal (pfSense) e alinhar a tabela `radcheck` do FreeRADIUS com base na view `dbo.HOTSPOT` do SQL Server.

## Estrutura

- `public/index.php` – front controller que serve a página do portal e expõe `/api/check` e `/healthz`.
- `app/Controllers` – controllers para o portal e API.
- `app/Services` – integração com SQL Server, MySQL/FreeRADIUS e auditoria.
- `app/Views/portal.php` – layout Bootstrap + Tailwind com JS para interceptar o submit e chamar o endpoint interno.
- `docs/` – planejamento completo (passos 1–9).

## Pré-requisitos

- PHP 8.2 com extensões `sqlsrv`, `pdo_sqlsrv`, `pdo_mysql`.
- MySQL (FreeRADIUS) acessível.
- SQL Server acessível na LAN.

## Configuração

1. Copie `.env.example` para `.env` e ajuste:
   ```bash
   cp .env.example .env
   ```
   - `SQLSERVER_*` – credenciais para a view `dbo.HOTSPOT`.
   - `MYSQL_*` – credenciais limitadas ao schema `radius`.
   - `FIXED_PASSWORD_MD5` – hash MD5 da senha fixa a ser aplicada.
   - `PORTAL_TOKEN` – token curto usado pelo JavaScript no pfSense.
   - `ALLOWED_IP` – IP do pfSense (default `192.168.121.1`).
   - `CORS_ALLOW_ORIGINS` – `*` (default) ou lista separada por vírgula com as origens autorizadas; defina `none` para delegar o CORS ao seu proxy/webserver.
   - `RADIUS_DISCONNECT_SECRET` – segredo de CoA/Disconnect no NAS para permitir desconexão imediata de sessão.
   - `RADIUS_DISCONNECT_PORT` – porta de CoA/Disconnect no NAS (geralmente `3799`).
   - `RADIUS_DISCONNECT_TIMEOUT` e `RADIUS_DISCONNECT_RETRIES` – timeout/tentativas do `radclient`.
   - `RADIUS_RADCLIENT_BIN` – caminho do binário `radclient`.
   - `PFSENSE_HOST`, `PFSENSE_SSH_PORT`, `PFSENSE_SSH_USER`, `PFSENSE_SSH_PASSWORD` – acesso SSH ao pfSense para executar a desconexão nativa do Captive Portal.
   - `PFSENSE_SSH_TIMEOUT` – timeout do comando SSH.
   - `PFSENSE_CAPTIVE_PORTAL_ZONE` – zona do Captive Portal usada na desconexão nativa.

2. Configure o VirtualHost/Apache para apontar `DocumentRoot` para `public/`.

3. Garanta permissões de escrita em `storage/logs/`.

## Uso

- Acesse `http://servidor/` para visualizar o portal.
- O JS intercepta o submit, chama `POST /api/check` e só libera o POST do pfSense se o backend responder `ok`.
- Endpoint `/healthz` retorna um JSON simples para monitoramento.

## Scripts adicionais

- Crie cron jobs para os processos descritos em `docs/passo6_operacoes.md` (limpeza/ressincronização).
- Ajuste o HTML final no pfSense conforme orientação do Passo 7.
