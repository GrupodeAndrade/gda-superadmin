<?php
declare(strict_types=1);
namespace Gda\Core\Database;

use PDO;

final class Connection
{
    private static ?PDO $instance = null;

    public static function fromConfig(array $config): PDO
    {
        if (self::$instance) return self::$instance;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
        return self::$instance = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
