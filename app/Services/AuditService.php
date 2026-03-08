<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

final class AuditService
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? __DIR__ . '/../../storage/logs/audit.log';
    }

    public function record(string $username, string $decision, string $originIp, string $reason, string $requestId): void
    {
        $entry = [
            'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
            'username' => $username,
            'decision' => $decision,
            'origin_ip' => $originIp,
            'reason' => $reason,
            'request_id' => $requestId,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
