<?php
declare(strict_types=1);

namespace App\Services;

final class Logger
{
    public static function activity(string $action, string $description, string $result = 'success', ?int $userId = null, array $metadata = []): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, result, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId ?? ($_SESSION['user_id'] ?? null),
            $action,
            $description,
            substr($_SERVER['REMOTE_ADDR'] ?? 'desconhecido', 0, 45),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $result,
            $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}

