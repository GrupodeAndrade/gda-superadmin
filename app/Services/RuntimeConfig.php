<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class RuntimeConfig
{
    private const DB_ENV = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    public static function load(?string $root = null, ?array $environment = null): array
    {
        $root ??= dirname(__DIR__, 2);
        $env = [];
        $present = [];

        foreach (self::DB_ENV as $name) {
            [$present[$name], $env[$name]] = self::environmentValue($name, $environment);
        }

        if (in_array(true, $present, true)) {
            foreach (self::DB_ENV as $name) {
                if (!$present[$name]) {
                    throw new RuntimeException('A configuração do banco por ambiente está incompleta.');
                }
            }

            $appUrl = self::optionalEnvironmentValue('APP_URL', $environment);
            return [
                'source' => 'environment',
                'configured' => true,
                'app_url' => self::validateAppUrl($appUrl),
                'db' => self::validateDatabase([
                    'host' => $env['DB_HOST'],
                    'port' => $env['DB_PORT'],
                    'name' => $env['DB_DATABASE'],
                    'user' => $env['DB_USERNAME'],
                    'password' => $env['DB_PASSWORD'],
                    'charset' => 'utf8mb4',
                ]),
            ];
        }

        $localFile = $root . '/config/local.php';
        if (!is_file($localFile)) {
            return ['source' => 'none', 'configured' => false, 'app_url' => null, 'db' => null];
        }

        $local = require $localFile;
        if (!is_array($local) || !isset($local['db']) || !is_array($local['db'])) {
            throw new RuntimeException('O arquivo de configuração local é inválido.');
        }

        return [
            'source' => 'local_file',
            'configured' => true,
            'app_url' => self::validateAppUrl(isset($local['app_url']) ? (string) $local['app_url'] : null),
            'db' => self::validateDatabase($local['db']),
        ];
    }

    private static function environmentValue(string $name, ?array $environment): array
    {
        if ($environment !== null) {
            return [array_key_exists($name, $environment), (string) ($environment[$name] ?? '')];
        }

        $value = getenv($name);
        return [$value !== false, $value === false ? '' : (string) $value];
    }

    private static function optionalEnvironmentValue(string $name, ?array $environment): ?string
    {
        [$present, $value] = self::environmentValue($name, $environment);
        return $present && trim($value) !== '' ? trim($value) : null;
    }

    private static function validateDatabase(array $db): array
    {
        foreach (['host', 'port', 'name', 'user'] as $key) {
            if (!array_key_exists($key, $db) || trim((string) $db[$key]) === '') {
                throw new RuntimeException('A configuração do banco está incompleta.');
            }
        }
        if (!array_key_exists('password', $db)) {
            throw new RuntimeException('A configuração do banco está incompleta.');
        }

        $port = filter_var($db['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new RuntimeException('A porta configurada para o banco é inválida.');
        }

        return [
            'host' => trim((string) $db['host']),
            'port' => $port,
            'name' => trim((string) $db['name']),
            'user' => trim((string) $db['user']),
            'password' => (string) $db['password'],
            'charset' => trim((string) ($db['charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
        ];
    }

    private static function validateAppUrl(?string $appUrl): ?string
    {
        if ($appUrl === null) return null;
        if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($appUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new RuntimeException('A URL pública configurada é inválida.');
        }
        return rtrim($appUrl, '/');
    }
}
