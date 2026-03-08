# Passo 9 – Observabilidade

## 9.1 Métricas principais
- Latência da consulta no SQL Server (p95) e latência total do endpoint (<150 ms alvo).
- Taxa de upserts por minuto e taxa de negações (usuário não encontrado).
- Contador de lock timeouts / falhas no GET_LOCK.
- Erros de conexão (SQL Server/MySQL) e falhas no read-after-write.

## 9.2 Coleta
- Exportar métricas via endpoint `/metrics` (Prometheus) ou enviar para uma stack ELK.
- Logs estruturados em JSON com `request_id`, tempos e decisão.

## 9.3 Alertas
- Alerta crítico se indisponibilidade de SQL Server ou MySQL > 1 min.
- Alerta se latência média exceder 150 ms por 5 minutos consecutivos.
- Alerta se taxa de negação subir acima de um limiar configurado (ex.: 20% em 10 min).

## 9.4 Dashboards
- Painel com: latências, throughput, % negações, locks, erros.
- Tabela com principais motivos de negação (para detectar inconsistências na view).

## 9.5 Logs/auditoria
- Cross-check entre tabela de auditoria e logs de aplicação.
- Retenção mínima de 90 dias; exportar amostras para o time de segurança conforme políticas internas.
