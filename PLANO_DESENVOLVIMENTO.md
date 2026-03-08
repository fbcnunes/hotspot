# Plano de Desenvolvimento do Captive Portal

## 1. Descoberta e Pré-requisitos
1.1 Validar conectividade pfSense ↔ servidor (HTTPS para endpoint interno, RADIUS).
1.2 Levantar versões/credenciais: FreeRADIUS, MySQL, Apache/PHP, SQL Server (view dbo.HOTSPOT).

## 2. Modelagem de Dados e Normalização
2.1 Revisar contrato da view (username minúsculo, formatos de senha, status, grupos, validade).
2.2 Definir rotina de normalização (trim, lowercase, bloqueio de caracteres de controle).
2.3 Mapear atributos necessários no radcheck (Cleartext-Password, NT-Password, Password-With-Header).
2.4 Desenhar tabela de auditoria (username, decisão, origem, timestamps, motivo).
2.5 Garantir índices no MySQL: UNIQUE (username, attribute) e índice simples em username.

## 3. Backend Interno (Endpoint de Verificação)
3.1 Implementar serviço PHP interno exposto apenas para pfSense (Apache + rota protegida).
3.2 Configurar pools: PDO_SQLSRV para SQL Server e PDO MySQL para FreeRADIUS.
3.3 Externalizar parâmetros em .env seguro (strings de conexão, timeouts, tokens).
3.4 Criar utilitários para normalização, logs estruturados e geração de IDs de correlação.
3.5 Registrar métricas de latência (consulta view, upsert), contadores de negação e erros.

## 4. Fluxo de Autorização
4.1 Receber login/senha → validar input → normalizar username.
4.2 Consultar view dbo.HOTSPOT pelo username (SELECT parametrizado).
4.3 Se não encontrado: decidir entre remoção de resíduos ou upsert Auth-Type := Reject; logar e responder deny.
4.4 Se encontrado e status ativo: selecionar formato de senha apropriado, validar validade/grupo.
4.5 Construir payload para upsert e resposta ok; caso status inapto, cair para fluxo de negação.

## 5. Upsert Transacional no MySQL
5.1 Obter lock específico: SELECT GET_LOCK(CONCAT('radcheck:', ?), 5).
5.2 Iniciar transação (START TRANSACTION).
5.3 Executar upsert conforme tipo de senha (Cleartext / NT / Password-With-Header).
5.4 Confirmar COMMIT e em seguida SELECT 1 FROM radcheck ... (read after write).
5.5 Se não confirmar, repetir leitura 2x com sleeps de 50 ms e 100 ms; falhando, registrar erro e abortar.
5.6 Liberar lock: SELECT RELEASE_LOCK(...).
5.7 Em falhas, ROLLBACK, liberar lock e gravar auditoria crítica.

## 6. Operações Complementares
6.1 Implementar limpeza diária opcional removendo entradas sem correspondência na view.
6.2 Criar job de ressincronização completa (batch) para cenários de falha prolongada.
6.3 Implementar endpoint de saúde e script de verificação (SQL Server + MySQL).
6.4 Documentar políticas de retenção de auditoria/logs e rotação de tokens/configs.

## 7. Integração com o Captive Portal
7.1 Alterar página pfSense para interceptar o submit com JavaScript.
7.2 Normalizar login no front-end antes de chamar o endpoint interno (fetch HTTPS + token).
7.3 Se resposta ok, liberar o POST original para o FreeRADIUS; caso negativo, exibir mensagem amigável.
7.4 Registrar no backend todas as decisões enviadas ao front-end.
7.5 Definir fallback: indisponibilidade do endpoint → negar autenticação com aviso “Serviço indisponível”.

## 8. Segurança
8.1 Restrição por IP (pfSense) e TLS obrigatório no endpoint.
8.2 Token curto (header) com rotação + validação de expiração/assinatura.
8.3 Usuário MySQL dedicado só com DML em radcheck/auditoria; usuário SQL Server com acesso somente leitura à view.
8.4 Harden do servidor (firewall, atualizações, fail2ban).
8.5 Logs sensíveis com permissões restritas e mascaramento de campos críticos.
8.6 Auditoria detalhada (username, status, origem, correlação, mensagens de erro).
8.7 Monitorar tentativas inválidas/replay e bloquear IPs suspeitos.

## 9. Observabilidade e Alertas
9.1 Expor métricas: latência view, tempo total do endpoint, taxa de upsert/negação, lock timeouts.
9.2 Criar alertas para: falha de SQL Server, falha de MySQL, erro > X%, tempo > 150 ms.
9.3 Dashboard (Grafana/ELK) para acompanhar tendência e auditorias.
9.4 Log estruturado com request-id para correlações e suporte.

## 10. Testes e Homologação
10.1 Testes unitários de normalização, parsing e DAL (SQL Server/MySQL).
10.2 Testes integrados simulando cenários: usuário presente, removido, expirado, duplicado, rede indisponível.
10.3 Testes de concorrência/lock com múltiplos logins simultâneos.
10.4 Teste end-to-end no pfSense apontando para ambiente de homologação.
10.5 Plano de rollback (remover JS do portal + limpar radcheck inserido).
10.6 Critérios de aceitação: latência <150 ms, 0 falhas em X acessos consecutivos.

## 11. Deploy e Operação
11.1 Automatizar instalação (drivers SQLSRV, dependências PHP, configs).
11.2 Checklist de deploy (certificados, tokens, variáveis, IPs).
11.3 Configurar cronjobs: limpeza diária, auditoria, health-check.
11.4 Executar smoke-test pós-deploy (script CLI + fluxo pfSense).
11.5 Monitorar primeiras 24h e ajustar thresholds/pool.
11.6 Documentar procedimentos de suporte, atualização e contingência.
