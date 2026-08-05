<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class InstallationState
{
    public const INSTALLED = 'installed';
    public const EMPTY = 'empty';
    public const PARTIAL = 'partial';

    private const ESSENTIAL_TABLES = [
        'users', 'clients', 'projects', 'client_projects', 'settings',
        'activity_logs', 'system_updates', 'migrations', 'login_attempts',
    ];

    public static function detect(PDO $database): string
    {
        try {
            $tables = $database->query(
                "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'"
            )->fetchAll(PDO::FETCH_COLUMN);
            if ($tables === []) return self::EMPTY;
            if (array_diff(self::ESSENTIAL_TABLES, array_map('strtolower', $tables)) !== []) return self::PARTIAL;
            $activeSuperAdmins = (int) $database->query(
                "SELECT COUNT(*) FROM users WHERE role='super_admin' AND status='active' AND deleted_at IS NULL"
            )->fetchColumn();
            return self::classify($tables, $activeSuperAdmins);
        } catch (PDOException $e) {
            throw new RuntimeException('Não foi possível verificar com segurança o estado do banco.', 0, $e);
        }
    }

    public static function classify(array $tables, int $activeSuperAdmins): string
    {
        if ($tables === []) return self::EMPTY;
        $missing = array_diff(self::ESSENTIAL_TABLES, array_map('strtolower', $tables));
        return $missing === [] && $activeSuperAdmins > 0 ? self::INSTALLED : self::PARTIAL;
    }

    public static function assertInstalledOrEmpty(string $state): void
    {
        if ($state === self::PARTIAL) {
            throw new RuntimeException('A estrutura do banco está incompleta; o instalador foi bloqueado.');
        }
        if (!in_array($state, [self::INSTALLED, self::EMPTY], true)) {
            throw new RuntimeException('Não foi possível determinar com segurança o estado da instalação.');
        }
    }
}
