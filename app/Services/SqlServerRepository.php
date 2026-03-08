<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use RuntimeException;

final class SqlServerRepository
{
    /** @var resource|null */
    private $connection;

    private const VIEW_NAME = 'dbo.HOTSPOT';

    /**
     * @return array<string,mixed>|null
     */
    public function findByLogin(string $login): ?array
    {
        $conn = $this->getConnection();
        $cpf = preg_replace('/\D+/', '', $login) ?? '';
        $matricula = $login;

        $sql = sprintf(
            'SELECT IDENTIFICADOR, MATRICULA, CPF, NOME, DATA_HORA FROM %s WHERE CPF = ? OR MATRICULA = ?',
            self::VIEW_NAME
        );
        $stmt = sqlsrv_prepare($conn, $sql, [$cpf, $matricula]);
        if (!$stmt || !sqlsrv_execute($stmt)) {
            throw new RuntimeException('Falha ao consultar SQL Server: ' . $this->formatSqlErrors());
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        return $row ?: null;
    }

    /**
     * @return resource
     */
    private function getConnection()
    {
        if ($this->connection) {
            return $this->connection;
        }
        $connectionInfo = [
            'Database' => Config::get('SQLSERVER_DATABASE'),
            'UID' => Config::get('SQLSERVER_USERNAME'),
            'PWD' => Config::get('SQLSERVER_PASSWORD'),
            'LoginTimeout' => 5,
            'CharacterSet' => 'UTF-8',
            'TrustServerCertificate' => true,
        ];

        $conn = sqlsrv_connect((string)Config::get('SQLSERVER_HOST'), $connectionInfo);
        if (!$conn) {
            throw new RuntimeException('Não foi possível conectar ao SQL Server: ' . $this->formatSqlErrors());
        }
        $this->connection = $conn;
        return $conn;
    }

    private function formatSqlErrors(): string
    {
        $errors = sqlsrv_errors();
        if (!$errors) {
            return 'Erro desconhecido';
        }
        $messages = array_map(
            fn($error) => sprintf('[%d] %s', $error['code'], $error['message']),
            $errors
        );
        return implode('; ', $messages);
    }
}
