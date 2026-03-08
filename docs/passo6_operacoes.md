# Passo 6 – Operações Complementares

## 6.1 Limpeza da `radcheck`
- Job diário (cron) executado em janela fora de pico.
- Query sugerida:
```
DELETE r
FROM radcheck r
LEFT JOIN (
    SELECT LOWER(REGEXP_REPLACE(CPF, '[^0-9a-z]', '')) AS username
    FROM OPENQUERY(SQLSERVER, 'SELECT CPF FROM dbo.HOTSPOT')
) v ON v.username = r.username
WHERE r.attribute = 'MD5-Password'
  AND v.username IS NULL;
```
- Como não podemos consultar diretamente via OPENQUERY, o job PHP pode reaproveitar a mesma lógica de leitura da view e realizar a limpeza localmente.

## 6.2 Ressincronização de contingência
- Script CLI que percorre toda a view `dbo.HOTSPOT`, aplica a normalização (CPF/matrícula) e reescreve as entradas na `radcheck`.
- Útil após manutenção no SQL Server ou inconsistências graves.

## 6.3 Health-check e monitoramento
- Endpoint `/healthz` já mencionado deve testar SQL Server + MySQL e retornar tempos.
- Adicionar alerta caso latência >150 ms ou qualquer banco fique indisponível.

## 6.4 Gestão de credenciais/configurações
- `.env` com permissões restritas (600) contendo strings de conexão, token do pfSense e hash MD5 fixo.

## 6.5 Logs e retenção
- Definir política de rotação (`logrotate`) para os logs JSON do endpoint.
- Auditoria armazenada no MySQL com retenção mínima exigida (ex.: 90 dias).
