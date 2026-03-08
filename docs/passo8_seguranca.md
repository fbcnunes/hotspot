# Passo 8 – Segurança

## 8.1 Controles de acesso
- Permitir tráfego para o endpoint apenas do IP 192.168.121.1 (pfSense) via firewall e validação em código.
- Rejeitar qualquer requisição sem token curto válido (`X-Auth-Token`).

## 8.2 Comunicação segura
- Exigir HTTPS entre pfSense e backend (certificado interno confiável pelo pfSense).
- Validar `Host`/`Origin` para mitigar CSRF.

## 8.3 Hardening do servidor
- Manter pacotes atualizados (Debian + PHP + drivers sqlsrv).
- Configurar firewall (UFW/iptables) limitando portas necessárias (80/443/1812/1813/3306).
- Ativar fail2ban para tentativas excessivas de SSH.

## 8.4 Gestão de credenciais
- `.env` só acessível ao usuário do serviço (permissão 600).
- Usuário MySQL restrito a DML em `radius.radcheck` e tabela de auditoria.
- Usuário SQL Server apenas com SELECT na view `dbo.HOTSPOT`.

## 8.5 Auditoria e logs
- Toda decisão (permitido/negado/erro) deve ir para a tabela de auditoria com request_id.
- Registrar IP de origem (deve ser sempre o pfSense); alertar se for diferente.
- Rotacionar logs via logrotate e enviar eventos críticos para syslog/ELK.

## 8.6 Resposta a incidentes
- Procedimento documentado para bloquear o endpoint (desabilitar JS ou firewall) em caso de comprometimento.
- Capacidade de invalidar rapidamente o token curto no backend.
