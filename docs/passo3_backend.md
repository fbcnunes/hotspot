# Passo 3 – Backend Interno (Endpoint de Verificação)

## 3.1 Arquitetura do serviço
- PHP 8.2 (já com `sqlsrv`/`pdo_sqlsrv`) rodando no Apache local.
- Endpoint exposto apenas na LAN e restrito ao IP 192.168.121.1 (pfSense) via firewall + verificação no código.
- Autenticação adicional com token curto (header) validado pelo backend.
- Configurações sensíveis centralizadas em `.env` (strings de conexão SQL Server/MySQL, token, timeout).

## 3.2 Conexão com SQL Server
- Usar PDO_SQLSRV ou `sqlsrv_connect` com pool de conexões.
- Timeout alvo: ≤ 150 ms por consulta (ajustar `LoginTimeout`/`QueryTimeout`).
- Consulta principal: `SELECT IDENTIFICADOR, MATRICULA, CPF, DATA_HORA FROM dbo.HOTSPOT WHERE CPF = :cpf OR MATRICULA = :matricula` (parametrizada).
- Sanitizar CPF removendo pontuação; matrícula convertida para string antes da comparação.

## 3.3 Conexão com MySQL (FreeRADIUS)
- PDO MySQL com `charset=utf8mb4`, modo `READ COMMITTED`, autocommit ligado.
- Usuário técnico com permissão apenas de DML em `radius.radcheck` e tabela de auditoria.
- Reutilizar conexões (pool/persistent) para minimizar latência.

## 3.4 Estrutura do endpoint
1. Recebe POST do JS com `{ username, password }` (senha será ignorada, mas pode ser usada para logs).
2. Normaliza username (trim, lowercase, regex) e rejeita se inválido.
3. Consulta SQL Server: procura match em CPF/matrícula. Se não achar, segue para negação.
4. Se achar, monta objeto de autorização (username normalizado, hash MD5 fixo, timestamp upstream).
5. Executa upsert transacional no MySQL (detalhado no Passo 5) e read-after-write.
6. Registra auditoria (`permitido`/`negado`/`erro`) com request_id.
7. Retorna JSON simples (`{ status: 'ok' }` ou `{ status: 'deny', reason: '...' }`).

## 3.5 Utilidades e logging
- Middleware para validar IP de origem (deve ser 192.168.121.1) e token.
- Gerar `request_id` (UUID v4) por requisição para correlacionar logs e auditoria.
- Logs estruturados (JSON) contendo tempos de SQL Server, MySQL e decisões.
- Expor endpoint `/healthz` que testa conexão em ambos os bancos e retorna métricas simples.
