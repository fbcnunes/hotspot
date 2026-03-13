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
            "SELECT u.*, ug.groupname
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

    private function hasUserFilters(array $filters): bool
    {
        foreach (['matricula', 'name', 'cpf', 'groupname'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
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
