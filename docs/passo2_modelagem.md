# Passo 2 – Modelagem de Dados e Normalização

## 2.1 Contrato da view SQL Server (`dbo.HOTSPOT`)
| Coluna        | Tipo       | Observações                                                         |
|---------------|------------|---------------------------------------------------------------------|
| IDENTIFICADOR | varchar    | Flag de status original (ex.: `S`). Não determina o acesso final.   |
| MATRICULA     | numeric    | Identificador primário; pode ser usado como username.               |
| NOME          | varchar    | Nome completo (logs/auditoria).                                     |
| CPF           | varchar    | Documento; também pode ser usado como username.                     |
| DATA_HORA     | datetime   | Timestamp da última atualização no upstream.                        |

- A view **não** fornece senha nem status definitivo e não poderá ser alterada. Ela apenas lista usuários autorizados; toda lógica adicional ficará no backend.
- Login informado pelo usuário (após normalização) será comparado com ambos os campos: se coincidir com CPF ou matrícula, o usuário segue para o UPSERT.

## 2.2 Normalização de credenciais
1. Receber `login` → aplicar `trim()` e converter para lowercase.
2. Bloquear caracteres de controle (`< 0x20`) e rejeitar caracteres fora de `^[a-z0-9._-]+$`.
3. Comparar login normalizado com `cpf` (remover pontuação antes de comparar) e com `matricula` (convertendo para string).  
4. Se houver match, usar o próprio login normalizado como `username` para o RADIUS; caso contrário, responder `deny`.
5. Como a view não fornece senha, inserir sempre uma senha fixa, representada por atributo `MD5-Password` no `radcheck` (ver item 2.3).
6. Para negações explícitas, gravar `Auth-Type := Reject`.

## 2.3 Estrutura `radcheck` (MySQL)
```
DESCRIBE radius.radcheck;
+----------+--------------+------+-----+---------+----------------+
| Field    | Type         | Null | Key | Default | Extra          |
+----------+--------------+------+-----+---------+----------------+
| id       | int unsigned | NO   | PRI | NULL    | auto_increment |
| username | varchar(64)  | NO   | MUL |         |                |
| attribute| varchar(64)  | NO   |     |         |                |
| op       | char(2)      | NO   |     | ==      |                |
| value    | varchar(253) | NO   |     |         |                |
+----------+--------------+------+-----+---------+----------------+
```
- Índices necessários: UNIQUE `(username, attribute)` para suportar o `ON DUPLICATE KEY`.
- Sempre gravar `op = ':='`; o valor padrão `==` será substituído no UPSERT.
- Senha utilizada será fixa (ex.: string `Portal123!`). Armazenaremos seu MD5 (`md5('Portal123!')`) e gravaremos:
  - `attribute = 'MD5-Password'`
  - `value = <hash_fixado>`

## 2.4 Auditoria proposta
| Campo          | Tipo        | Descrição                                  |
|----------------|-------------|--------------------------------------------|
| id             | BIGINT PK   | Auto increment.                            |
| username       | VARCHAR(64) | Username normalizado.                      |
| decisao        | ENUM        | `permitido`, `negado`, `erro`.             |
| origem_ip      | VARCHAR(45) | IP do pfSense (deve ser 192.168.121.1).    |
| motivo         | TEXT        | Mensagem resumida (ex.: status inativo).   |
| created_at     | DATETIME    | Timestamp do evento.                       |
| request_id     | CHAR(36)    | UUID para correlação com logs.            |

## 2.5 Próximos passos
- SQL Server não sofrerá alterações em hipótese alguma: `dbo.HOTSPOT` permanece como única fonte e apenas indica quem está autorizado; toda lógica adicional ocorre no backend.  
- Definir regex e mensagens de validação para inputs inválidos antes de chamar o endpoint.  
- Modelar stored procedures (opcional) para obter a senha/hash correta caso haja múltiplos formatos.
