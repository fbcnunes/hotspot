# Passo 1 – Descoberta e Pré-requisitos

## 1.1 Conectividade pfSense ↔ Servidor
- Interface principal ens33 configurada com 192.168.121.10/24.
- Gateway/pfSense presumido em 192.168.121.1; ping respondeu (3/3 pacotes, ~0.48 ms), indicando rota ativa na LAN.
- Portas locais relevantes abertas: RADIUS UDP 1812/1813 (FreeRADIUS), MySQL 3306/33060, HTTP 80 (Apache).
- Captive portal irá interceptar o submit via JavaScript customizado na página (necessário validar permissões e assets no pfSense).

## 1.2 Inventário de Componentes Locais
| Componente | Versão / Build | Observações |
|------------|----------------|-------------|
| FreeRADIUS | 3.2.1 (/usr/sbin/freeradius -v) | Serviço freeradius.service ouvindo em 0.0.0.0:1812/1813 (UDP). |
| MySQL | 8.4.6 Community (mysql --version) | Usuário fernando com acesso confirmado; tabela radcheck disponível. |
| Apache HTTP | 2.4.65 (Debian build 2025-07-29) | Serviço ativo e reiniciado após instalação dos drivers SQLSRV. |
| PHP | 8.2.29 CLI (Zend Engine 4.2.29) | Extensões sqlsrv e pdo_sqlsrv habilitadas. |
| SQL Server remoto | 2016 SP3 CU1 GDR (13.0.7050.2) em 192.168.121.2 | Acesso via usuário hotspot (senha fornecida); view dbo.HOTSPOT consultada. |

### Observações complementares
- Endpoint SQL Server respondeu em ~4 s via sqlsrv_connect; ajustar timeouts ao implementar o pool.
- Necessário definir armazenamento seguro de credenciais (.env/secret store) antes do deploy.
- URL do captive portal confirmada: http://192.168.121.1:8002 (HTTP); tráfego do endpoint será permitido apenas para o IP do pfSense.

## 1.3 Referência do formulário no pfSense
Upload de página customizada exige HTML/PHP com formulário que faça POST para `$PORTAL_ACTION$`, contendo:
- Campo `redirurl` (hidden) com valor `$PORTAL_REDIRURL$`.
- Campos `auth_user`/`auth_pass` e/ou `auth_voucher` conforme autenticação habilitada.
- Botão submit `accept` e hidden `zone` se necessário.

Exemplo oficial:
```
<form method="post" action="$PORTAL_ACTION$">
   <input name="auth_user" type="text">
   <input name="auth_pass" type="password">
   <input name="auth_voucher" type="text">
   <input name="redirurl" type="hidden" value="$PORTAL_REDIRURL$">
   <input name="zone" type="hidden" value="$PORTAL_ZONE$">
   <input name="accept" type="submit" value="Continue">
</form>
```

