# Passo 4 – Fluxo de Autorização

1. **Receber credenciais**
   - JS envia `username` normalizado (trim/lowercase) e `password` digitada (opcional para registrar tentativa).
   - Backend valida o payload, verifica IP e token.

2. **Normalização**
   - Regex `^[a-z0-9._-]+$` pós-lowercase.
   - Remover pontuação/espacos de CPF para comparação.

3. **Consulta na view**
   - Query parametrizada em `dbo.HOTSPOT` buscando `CPF = :cpf` OU `MATRICULA = :matricula`.
   - Se não houver match → auditoria (`negado`, motivo `usuario_nao_autorizado`) + retorno `{ status: 'deny' }` + opcional inserir `Auth-Type := Reject`.

4. **Match encontrado**
   - Selecionar username final (o próprio login normalizado).
   - Preparar senha fixa: `md5('Portal123!') = 6a4a677c5d7a43e02dff12cd1f8b0c83` (exemplo).
   - Seguir para Passo 5 (upsert transacional) com atributo `MD5-Password` e o hash acima.

5. **Resposta**
   - Após read-after-write positivo, retornar `{ status: 'ok' }` ao JS.
   - Caso algum passo falhe, registrar `erro` na auditoria e responder `{ status: 'error', message: '...' }`.

# Passo 5 – Upsert no MySQL

1. `SELECT GET_LOCK(CONCAT('radcheck:', :username), 5)`; abortar se `NULL`.
2. `START TRANSACTION`.
3. `INSERT ... ON DUPLICATE KEY UPDATE` com:
   ```sql
   INSERT INTO radcheck(username, attribute, op, value)
   VALUES(:username, 'MD5-Password', ':=', '6a4a677c5d7a43e02dff12cd1f8b0c83')
   ON DUPLICATE KEY UPDATE value=VALUES(value), op=VALUES(op);
   ```
4. `COMMIT`.
5. `SELECT 1 FROM radcheck WHERE username=:username AND attribute='MD5-Password'`.
   - Se não achar, retry até 2 vezes (sleep 50 ms e 100 ms).
6. `SELECT RELEASE_LOCK(CONCAT('radcheck:', :username))`.
7. Em caso de erro: `ROLLBACK`, liberar lock e registrar auditoria (`erro`, motivo detalhado). Opcional: gravar `Auth-Type := Reject` para bloquear futuras tentativas até correção.
