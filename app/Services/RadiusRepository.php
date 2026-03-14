<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use PDO;
use PDOException;
use RuntimeException;

final class RadiusRepository
{
    private ?PDO $pdo = null;

    public function upsertCleartextPassword(string $username, string $password): void
    {
        $pdo = $this->getConnection();
        $lockKey = 'radcheck:' . $username;

        $lockStmt = $pdo->prepare('SELECT GET_LOCK(:lock_key, 5) AS acquired');
        $lockStmt->execute(['lock_key' => $lockKey]);
        $lock = $lockStmt->fetchColumn();
        if ((int)$lock !== 1) {
            throw new RuntimeException('Não foi possível obter lock para o usuário.');
        }

        try {
            $pdo->beginTransaction();
            
            // 1. Atualizar a senha
            $stmt = $pdo->prepare(
                "INSERT INTO radcheck(username, attribute, op, value)
                 VALUES(:username, 'Cleartext-Password', ':=', :value)
                 ON DUPLICATE KEY UPDATE value=VALUES(value), op=VALUES(op)"
            );
            $stmt->execute([
                'username' => $username,
                'value' => $password,
            ]);

            // 2. Vincular ao grupo padrao se não houver vínculo
            $groupStmt = $pdo->prepare(
                "INSERT INTO radusergroup(username, groupname, priority) 
                 SELECT :username, 'padrao', 1 
                 WHERE NOT EXISTS (
                     SELECT 1 FROM radusergroup WHERE username = :username_check
                 )"
            );
            $groupStmt->execute([
                'username' => $username,
                'username_check' => $username,
            ]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $this->releaseLock($lockKey);
            throw new RuntimeException('Erro ao executar upsert: ' . $e->getMessage(), 0, $e);
        }

        $this->assertReadAfterWrite($username);
        $this->releaseLock($lockKey);
    }

    private function assertReadAfterWrite(string $username): void
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT 1 FROM radcheck WHERE username = :username AND attribute = 'Cleartext-Password' LIMIT 1"
        );
        $retries = [0, 50000, 100000]; // microseconds
        foreach ($retries as $sleep) {
            if ($sleep > 0) {
                usleep($sleep);
            }
            $stmt->execute(['username' => $username]);
            if ($stmt->fetchColumn()) {
                return;
            }
        }
        throw new RuntimeException('Read-after-write falhou para o usuário ' . $username);
    }

    public function getActiveSessions(): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->query(
            "SELECT a.radacctid, a.username, a.nasipaddress, a.framedipaddress, 
                    a.acctstarttime, a.acctsessiontime, a.acctinputoctets, a.acctoutputoctets,
                    u.name, u.cpf, u.matricula
             FROM radacct a
             LEFT JOIN hotspot_users u ON a.username = u.username
             WHERE a.acctstoptime IS NULL 
             ORDER BY a.acctstarttime DESC"
        );
        return $stmt->fetchAll();
    }

    public function getConsumptionHistory(int $limit = 50): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT a.username, 
                    SUM(a.acctinputoctets) as download, 
                    SUM(a.acctoutputoctets) as upload,
                    COUNT(*) as session_count,
                    MAX(a.acctstarttime) as last_access,
                    u.name, u.cpf, u.matricula
             FROM radacct a
             LEFT JOIN hotspot_users u ON a.username = u.username
             GROUP BY a.username, u.name, u.cpf, u.matricula
             ORDER BY last_access DESC 
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getUsers(): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->query(
            "SELECT u.*, ug.groupname 
             FROM hotspot_users u
             LEFT JOIN radusergroup ug ON u.username = ug.username
             ORDER BY u.name ASC"
        );
        return $stmt->fetchAll();
    }

    public function searchUsers(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        if (!$this->hasUserFilters($filters)) {
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => 0,
            ];
        }

        $pdo = $this->getConnection();
        [$whereSql, $params] = $this->buildUserFilters($filters);

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) 
             FROM hotspot_users u
             LEFT JOIN radusergroup ug ON u.username = ug.username
             {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT u.*, ug.groupname,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM radcheck rc
                            WHERE rc.username = u.username
                              AND rc.attribute = 'Auth-Type'
                              AND rc.value = 'Reject'
                        ) THEN 1
                        ELSE 0
                    END AS is_blocked,
                    (
                        SELECT COUNT(*)
                        FROM radacct ra
                        WHERE ra.username = u.username
                          AND ra.acctstoptime IS NULL
                    ) AS active_sessions_count
             FROM hotspot_users u
             LEFT JOIN radusergroup ug ON u.username = ug.username
             {$whereSql}
             ORDER BY u.name ASC
             LIMIT :limit OFFSET :offset"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    public function getActiveSessionByUsername(string $username): ?array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT radacctid, username, nasipaddress, framedipaddress, acctsessionid, acctstarttime
             FROM radacct
             WHERE username = :username
               AND acctstoptime IS NULL
             ORDER BY acctstarttime DESC
             LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $session = $stmt->fetch();

        return $session === false ? null : $session;
    }

    public function getActiveSessionsByUsername(string $username): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT radacctid, username, nasipaddress, framedipaddress, acctsessionid, acctstarttime
             FROM radacct
             WHERE username = :username
               AND acctstoptime IS NULL
             ORDER BY acctstarttime DESC"
        );
        $stmt->execute(['username' => $username]);

        return $stmt->fetchAll();
    }

    public function blockUserAuth(string $username): void
    {
        $pdo = $this->getConnection();
        try {
            $pdo->beginTransaction();
            $deleteStmt = $pdo->prepare(
                "DELETE FROM radcheck
                 WHERE username = :username
                   AND attribute = 'Auth-Type'"
            );
            $deleteStmt->execute(['username' => $username]);

            $insertStmt = $pdo->prepare(
                "INSERT INTO radcheck (username, attribute, op, value)
                 VALUES (:username, 'Auth-Type', ':=', 'Reject')"
            );
            $insertStmt->execute(['username' => $username]);
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Erro ao bloquear autenticação do usuário: ' . $e->getMessage(), 0, $e);
        }
    }

    public function unblockUserAuth(string $username): void
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "DELETE FROM radcheck
             WHERE username = :username
               AND attribute = 'Auth-Type'
               AND value = 'Reject'"
        );
        $stmt->execute(['username' => $username]);
    }

    public function disconnectUser(string $username): array
    {
        $sessions = $this->getActiveSessionsByUsername($username);
        $hasActiveSession = $sessions !== [];
        $disconnectAttempted = false;
        $disconnectSuccess = false;
        $disconnectSuccessCount = 0;

        if ($hasActiveSession) {
            $disconnectAttempted = true;
            foreach ($sessions as $session) {
                if ($this->tryNasDisconnect($session)) {
                    $disconnectSuccessCount++;
                }
            }
            $disconnectSuccess = $disconnectSuccessCount === count($sessions);
        }

        $this->blockUserAuth($username);

        return [
            'blocked' => true,
            'disconnect_attempted' => $disconnectAttempted,
            'disconnect_success' => $disconnectSuccess,
            'has_active_session' => $hasActiveSession,
            'active_session_count' => count($sessions),
            'disconnect_success_count' => $disconnectSuccessCount,
        ];
    }

    public function disconnectSessionByRadacctId(int $radacctId): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT radacctid, username, nasipaddress, framedipaddress, acctsessionid, acctstarttime
             FROM radacct
             WHERE radacctid = :radacctid
               AND acctstoptime IS NULL
             LIMIT 1"
        );
        $stmt->execute(['radacctid' => $radacctId]);
        $session = $stmt->fetch();

        if ($session === false) {
            return [
                'attempted' => false,
                'success' => false,
                'already_closed' => true,
            ];
        }

        return [
            'attempted' => true,
            'success' => $this->tryNasDisconnect($session),
            'already_closed' => false,
        ];
    }

    public function isUserAuthBlocked(string $username): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM radcheck
             WHERE username = :username
               AND attribute = 'Auth-Type'
               AND value = 'Reject'
             LIMIT 1"
        );
        $stmt->execute(['username' => $username]);

        return (bool) $stmt->fetchColumn();
    }

    public function setUserGroup(string $username, string $groupname): void
    {
        $pdo = $this->getConnection();
        try {
            $pdo->beginTransaction();
            
            // Remover vínculos anteriores
            $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE username = :username");
            $stmt->execute(['username' => $username]);

            // Adicionar novo vínculo
            $stmt = $pdo->prepare(
                "INSERT INTO radusergroup (username, groupname, priority) 
                 VALUES (:username, :group, 1)"
            );
            $stmt->execute([
                'username' => $username,
                'group' => $groupname
            ]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new RuntimeException('Erro ao associar grupo: ' . $e->getMessage());
        }
    }

    public function getProfiles(): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT * FROM hotspot_profiles ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function saveProfile(array $data): void
    {
        $pdo = $this->getConnection();
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare(
                "INSERT INTO hotspot_profiles (name, download_kbps, upload_kbps, session_timeout_seconds, idle_timeout_seconds, redir_url)
                 VALUES (:name, :down, :up, :timeout, :idle, :redir)
                 ON DUPLICATE KEY UPDATE 
                    download_kbps = VALUES(download_kbps),
                    upload_kbps = VALUES(upload_kbps),
                    session_timeout_seconds = VALUES(session_timeout_seconds),
                    idle_timeout_seconds = VALUES(idle_timeout_seconds),
                    redir_url = VALUES(redir_url)"
            );
            $stmt->execute([
                'name' => $data['name'],
                'down' => $data['download_kbps'],
                'up' => $data['upload_kbps'],
                'timeout' => $data['session_timeout_seconds'],
                'idle' => $data['idle_timeout_seconds'],
                'redir' => $data['redir_url'] ?? ''
            ]);

            // Sync with radgroupreply
            $this->syncProfileToRadius($data['name'], $data);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new RuntimeException('Erro ao salvar perfil: ' . $e->getMessage());
        }
    }

    private function syncProfileToRadius(string $groupName, array $data): void
    {
        $pdo = $this->getConnection();
        
        // Limpar atributos antigos do grupo
        $stmt = $pdo->prepare("DELETE FROM radgroupreply WHERE groupname = :group");
        $stmt->execute(['group' => $groupName]);

        // Inserir novos atributos
        $attrs = [
            ['attribute' => 'Wispr-Bandwidth-Max-Down', 'value' => ($data['download_kbps'] * 1024), 'type' => 'int'],
            ['attribute' => 'Wispr-Bandwidth-Max-Up', 'value' => ($data['upload_kbps'] * 1024), 'type' => 'int'],
            ['attribute' => 'Session-Timeout', 'value' => $data['session_timeout_seconds'], 'type' => 'int'],
            ['attribute' => 'Idle-Timeout', 'value' => $data['idle_timeout_seconds'], 'type' => 'int'],
            ['attribute' => 'Wispr-Redirection-URL', 'value' => $data['redir_url'] ?? '', 'type' => 'string'],
        ];

        $stmt = $pdo->prepare(
            "INSERT INTO radgroupreply (groupname, attribute, op, value) 
             VALUES (:group, :attr, ':=', :val)"
        );

        foreach ($attrs as $attr) {
            $val = $attr['value'];
            $shouldInsert = ($attr['type'] === 'int' && $val > 0) || ($attr['type'] === 'string' && !empty($val));
            
            if ($shouldInsert) {
                $stmt->execute([
                    'group' => $groupName,
                    'attr' => $attr['attribute'],
                    'val' => (string)$val
                ]);
            }
        }
    }

    public function getSettings(): array
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM hotspot_settings");
        $results = $stmt->fetchAll();
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function updateSetting(string $key, string $value): void
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO hotspot_settings (setting_key, setting_value) 
             VALUES (:key, :val) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute(['key' => $key, 'val' => $value]);
    }

    public function syncUserMetadata(string $username, array $data): void
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO hotspot_users (username, name, cpf, matricula)
             VALUES (:username, :name, :cpf, :matricula)
             ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                cpf = VALUES(cpf),
                matricula = VALUES(matricula),
                last_login = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            'username' => $username,
            'name' => $data['NOME'] ?? '',
            'cpf' => $data['CPF'] ?? '',
            'matricula' => $data['MATRICULA'] ?? '',
        ]);
    }

    private function releaseLock(string $lockKey): void
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_key)');
        $stmt->execute(['lock_key' => $lockKey]);
    }

    private function tryNasDisconnect(array $session): bool
    {
        $nativeResult = $this->tryPfSenseNativeDisconnect($session);
        if ($nativeResult !== null) {
            return $nativeResult;
        }

        // Fallback para ambientes onde a desconexão nativa do pfSense não estiver configurada.
        return $this->tryRadiusDisconnect($session);
    }

    private function tryPfSenseNativeDisconnect(array $session): ?bool
    {
        $host = trim((string) Config::get('PFSENSE_HOST', ''));
        $user = trim((string) Config::get('PFSENSE_SSH_USER', ''));
        $password = (string) Config::get('PFSENSE_SSH_PASSWORD', '');
        $zone = trim((string) Config::get('PFSENSE_CAPTIVE_PORTAL_ZONE', ''));

        if ($host === '' || $user === '' || $password === '' || $zone === '') {
            return null;
        }

        $sessionId = trim((string) ($session['acctsessionid'] ?? ''));
        if ($sessionId === '') {
            error_log('[hotspot] tryPfSenseNativeDisconnect: acctsessionid ausente.');
            return false;
        }

        $port = (int) (Config::get('PFSENSE_SSH_PORT', '22') ?? '22');
        if ($port <= 0 || $port > 65535) {
            $port = 22;
        }

        $timeout = (int) (Config::get('PFSENSE_SSH_TIMEOUT', '8') ?? '8');
        if ($timeout <= 0) {
            $timeout = 8;
        }

        $askPassPath = tempnam(sys_get_temp_dir(), 'pfssh-');
        if ($askPassPath === false) {
            error_log('[hotspot] tryPfSenseNativeDisconnect: falha ao criar script askpass.');
            return false;
        }

        $phpCode = sprintf(
            'require_once("/etc/inc/config.inc"); require_once("/etc/inc/captiveportal.inc"); $cpzone=%s; $sessionId=%s; $escapedSessionId = SQLite3::escapeString($sessionId); $before = captiveportal_read_db("WHERE sessionid = \'" . $escapedSessionId . "\'"); if (count($before) === 0) { fwrite(STDOUT, "SESSION_NOT_FOUND\n"); exit(2); } captiveportal_disconnect_client($sessionId, 6, "DISCONNECT - ADMIN PORTAL"); $after = captiveportal_read_db("WHERE sessionid = \'" . $escapedSessionId . "\'"); if (count($after) === 0) { fwrite(STDOUT, "DISCONNECT_OK\n"); exit(0); } fwrite(STDOUT, "DISCONNECT_FAILED\n"); exit(1);',
            var_export($zone, true),
            var_export($sessionId, true)
        );
        $remoteScript = 'php -r ' . escapeshellarg($phpCode);

        $askPassScript = "#!/bin/sh\nprintf '%s\\n' " . escapeshellarg($password) . "\n";
        file_put_contents($askPassPath, $askPassScript);
        chmod($askPassPath, 0700);

        $command = sprintf(
            'env DISPLAY=:0 SSH_ASKPASS=%s SSH_ASKPASS_REQUIRE=force setsid ssh -T -o StrictHostKeyChecking=no -o UserKnownHostsFile=/root/.ssh/known_hosts -o PreferredAuthentications=password,keyboard-interactive -o PubkeyAuthentication=no -o NumberOfPasswordPrompts=1 -o ConnectTimeout=%d -p %d %s@%s %s',
            escapeshellarg($askPassPath),
            $timeout,
            $port,
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($remoteScript)
        );

        $result = $this->runProcess($command);

        @unlink($askPassPath);

        $response = trim(strtolower($result['stdout'] . "\n" . $result['stderr']));
        if ($result['exit_code'] === 0 && str_contains($response, 'disconnect_ok')) {
            return true;
        }

        error_log(
            sprintf(
                '[hotspot] tryPfSenseNativeDisconnect: falha (exit=%d, host=%s, zone=%s, session=%s). output=%s',
                $result['exit_code'],
                $host,
                $zone,
                $sessionId,
                substr(trim($result['stdout'] . ' ' . $result['stderr']), 0, 400)
            )
        );

        return false;
    }

    private function tryRadiusDisconnect(array $session): bool
    {
        $nasIp = trim((string) ($session['nasipaddress'] ?? ''));
        if ($nasIp === '' || filter_var($nasIp, FILTER_VALIDATE_IP) === false) {
            error_log('[hotspot] tryNasDisconnect: nasipaddress ausente/invalido.');
            return false;
        }

        $secret = trim((string) Config::get('RADIUS_DISCONNECT_SECRET', ''));
        if ($secret === '') {
            error_log('[hotspot] tryNasDisconnect: RADIUS_DISCONNECT_SECRET nao configurado.');
            return false;
        }

        $port = (int) (Config::get('RADIUS_DISCONNECT_PORT', '3799') ?? '3799');
        if ($port <= 0 || $port > 65535) {
            $port = 3799;
        }

        $timeout = (int) (Config::get('RADIUS_DISCONNECT_TIMEOUT', '3') ?? '3');
        if ($timeout <= 0) {
            $timeout = 3;
        }

        $retries = (int) (Config::get('RADIUS_DISCONNECT_RETRIES', '1') ?? '1');
        if ($retries < 0) {
            $retries = 1;
        }

        $radclientBin = trim((string) Config::get('RADIUS_RADCLIENT_BIN', '/usr/bin/radclient'));
        if ($radclientBin === '') {
            $radclientBin = '/usr/bin/radclient';
        }

        $attributes = [];
        $username = trim((string) ($session['username'] ?? ''));
        if ($username !== '') {
            $attributes[] = 'User-Name="' . addcslashes($username, "\\\"") . '"';
        }

        $acctSessionId = trim((string) ($session['acctsessionid'] ?? ''));
        if ($acctSessionId !== '') {
            $attributes[] = 'Acct-Session-Id="' . addcslashes($acctSessionId, "\\\"") . '"';
        }

        $framedIp = trim((string) ($session['framedipaddress'] ?? ''));
        if ($framedIp !== '' && filter_var($framedIp, FILTER_VALIDATE_IP) !== false) {
            $attributes[] = 'Framed-IP-Address=' . $framedIp;
        }

        if ($username === '' && $acctSessionId === '' && $framedIp === '') {
            error_log('[hotspot] tryNasDisconnect: sem atributos de sessao para enviar.');
            return false;
        }

        // NAS-IP-Address pode causar rejeição/parse em alguns NAS se enviado em Disconnect-Request.
        // Mantemos o payload mínimo com identificadores de sessão.

        $payload = implode("\n", $attributes) . "\n\n";
        $target = $nasIp . ':' . $port;
        $command = sprintf(
            '%s -x -r %d -t %d %s disconnect %s',
            escapeshellarg($radclientBin),
            $retries,
            $timeout,
            escapeshellarg($target),
            escapeshellarg($secret)
        );

        $spec = [
            0 => ['pipe', 'w'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($command, $spec, $pipes);
        if (!is_resource($proc)) {
            error_log('[hotspot] tryNasDisconnect: falha ao iniciar radclient.');
            return false;
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);
        $response = strtolower((string) ($stdout . "\n" . $stderr));

        if ($exitCode === 0 && (str_contains($response, 'disconnect-ack') || str_contains($response, 'received response id'))) {
            return true;
        }

        error_log(
            sprintf(
                '[hotspot] tryNasDisconnect: falha (exit=%d, target=%s). output=%s',
                $exitCode,
                $target,
                substr(trim($stdout . ' ' . $stderr), 0, 300)
            )
        );

        return false;
    }

    /**
     * @return array{stdout:string,stderr:string,exit_code:int}
     */
    private function runProcess(string $command): array
    {
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($command, $spec, $pipes);
        if (!is_resource($proc)) {
            return [
                'stdout' => '',
                'stderr' => 'failed to start process',
                'exit_code' => 255,
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'exit_code' => proc_close($proc),
        ];
    }

    private function hasUserFilters(array $filters): bool
    {
        foreach (['matricula', 'name', 'cpf', 'groupname'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        $blockedStatus = trim((string) ($filters['blocked_status'] ?? 'all'));
        if (in_array($blockedStatus, ['blocked', 'unblocked'], true)) {
            return true;
        }

        $sessionStatus = trim((string) ($filters['session_status'] ?? 'all'));
        if (in_array($sessionStatus, ['active', 'inactive'], true)) {
            return true;
        }

        return false;
    }

    private function buildUserFilters(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (trim((string) ($filters['matricula'] ?? '')) !== '') {
            $conditions[] = 'u.matricula LIKE :matricula';
            $params[':matricula'] = '%' . trim((string) $filters['matricula']) . '%';
        }

        if (trim((string) ($filters['name'] ?? '')) !== '') {
            $conditions[] = 'u.name LIKE :name';
            $params[':name'] = '%' . trim((string) $filters['name']) . '%';
        }

        if (trim((string) ($filters['cpf'] ?? '')) !== '') {
            $conditions[] = 'u.cpf LIKE :cpf';
            $params[':cpf'] = '%' . trim((string) $filters['cpf']) . '%';
        }

        if (trim((string) ($filters['groupname'] ?? '')) !== '') {
            $conditions[] = 'ug.groupname = :groupname';
            $params[':groupname'] = trim((string) $filters['groupname']);
        }

        $blockedStatus = trim((string) ($filters['blocked_status'] ?? 'all'));
        if ($blockedStatus === 'blocked') {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM radcheck rc
                WHERE rc.username = u.username
                  AND rc.attribute = 'Auth-Type'
                  AND rc.value = 'Reject'
            )";
        } elseif ($blockedStatus === 'unblocked') {
            $conditions[] = "NOT EXISTS (
                SELECT 1
                FROM radcheck rc
                WHERE rc.username = u.username
                  AND rc.attribute = 'Auth-Type'
                  AND rc.value = 'Reject'
            )";
        }

        $sessionStatus = trim((string) ($filters['session_status'] ?? 'all'));
        if ($sessionStatus === 'active') {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM radacct ra
                WHERE ra.username = u.username
                  AND ra.acctstoptime IS NULL
            )";
        } elseif ($sessionStatus === 'inactive') {
            $conditions[] = "NOT EXISTS (
                SELECT 1
                FROM radacct ra
                WHERE ra.username = u.username
                  AND ra.acctstoptime IS NULL
            )";
        }

        $whereSql = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return [$whereSql, $params];
    }

    private function getConnection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::get('MYSQL_HOST', '127.0.0.1'),
            Config::get('MYSQL_PORT', '3306'),
            Config::get('MYSQL_DATABASE')
        );

        $pdo = new PDO($dsn, (string)Config::get('MYSQL_USERNAME'), (string)Config::get('MYSQL_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo = $pdo;
        return $pdo;
    }
}
