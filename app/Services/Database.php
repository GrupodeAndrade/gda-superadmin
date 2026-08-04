<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }
        $file = dirname(__DIR__, 2) . '/config/local.php';
        if (!is_file($file)) {
            throw new RuntimeException('A aplicação ainda não foi instalada.');
        }
        $config = require $file;
        $db = $config['db'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset'] ?? 'utf8mb4');
        try {
            self::$connection = new PDO($dsn, $db['user'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Não foi possível conectar ao banco de dados.', 0, $e);
        }
        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }
}

