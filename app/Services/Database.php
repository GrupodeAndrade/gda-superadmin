<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class DatabaseConnectionException extends RuntimeException
{
    public const INVALID_CREDENTIALS = 'invalid_credentials';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $reason, ?PDOException $previous = null)
    {
        parent::__construct(
            $reason === self::INVALID_CREDENTIALS
                ? 'As credenciais do banco foram recusadas.'
                : 'O banco de dados está indisponível.',
            0,
            $previous
        );
    }
}

final class Database
{
    private static ?PDO $connection = null;
    private static ?array $runtime = null;

    public static function configure(array $runtime): void
    {
        self::$runtime = $runtime;
        self::$connection = null;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }
        $runtime = self::$runtime ?? RuntimeConfig::load();
        if (empty($runtime['configured']) || !is_array($runtime['db'] ?? null)) {
            throw new RuntimeException('A aplicação ainda não possui configuração de banco.');
        }
        $db = $runtime['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset'] ?? 'utf8mb4');
        try {
            self::$connection = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new DatabaseConnectionException(self::classifyConnectionFailure($e), $e);
        }
        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
        self::$runtime = null;
    }

    public static function classifyConnectionFailure(PDOException $exception): string
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        return in_array($driverCode, [1044, 1045, 1698], true)
            ? DatabaseConnectionException::INVALID_CREDENTIALS
            : DatabaseConnectionException::UNAVAILABLE;
    }
}
