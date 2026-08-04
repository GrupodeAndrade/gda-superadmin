<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

use PDO;
use Gda\ClientArea\ClientContext;

final class SettingsService
{
    private array $cache = [];
    public function __construct(private PDO $db, private ClientContext $context) {}
    public function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];
        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE company_id=? AND setting_key=? LIMIT 1');
        $stmt->execute([$this->context->companyId(),$key]); $value = $stmt->fetchColumn();
        return $this->cache[$key] = $value === false ? $default : (string)$value;
    }
    public function set(string $key, ?string $value): void
    {
        $stmt = $this->db->prepare('INSERT INTO app_settings(company_id,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $stmt->execute([$this->context->companyId(),$key,$value]); $this->cache[$key] = $value;
    }
}
