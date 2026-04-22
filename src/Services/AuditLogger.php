<?php
declare(strict_types=1);

namespace App\Services;

final class AuditLogger
{
    private string $logPath;

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;
        $directory = dirname($logPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $action, string $status, array $context = []): void
    {
        $record = [
            'timestamp' => date('c'),
            'action' => $action,
            'status' => $status,
            'ip' => client_ip(),
            'user_agent' => client_user_agent(),
            'context' => $context,
        ];

        $json = json_encode($record, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        file_put_contents($this->logPath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        if (!is_file($this->logPath)) {
            return [];
        }

        $lines = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $sliced = array_slice($lines, -$limit);
        $events = [];

        foreach (array_reverse($sliced) as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }
}
