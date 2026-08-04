<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class ModuleMigrationService
{
    private const MIGRATION_ID = '20260801_register_gda_caixas';

    public static function run(): void
    {
        $db = Database::connection();
        $done = $db->prepare("SELECT COUNT(*) FROM migrations WHERE migration_id=? AND status='executed'");
        $done->execute([self::MIGRATION_ID]);
        if ((int) $done->fetchColumn() > 0) return;

        try {
            self::ensureProjectSchema($db);
            $db->beginTransaction();
            $canonical = $db->query("SELECT * FROM projects WHERE code='gda-caixas' AND deleted_at IS NULL LIMIT 1")->fetch();
            $legacy = $db->query("SELECT * FROM projects WHERE deleted_at IS NULL AND (code IN ('caixa-diario','caixa_diario','gda-caixa-diario') OR name='Caixa Diário') ORDER BY id LIMIT 1")->fetch();
            $projectId = null;

            if ($legacy) {
                $projectId = (int) $legacy['id'];
                if ($canonical && (int) $canonical['id'] !== $projectId) {
                    $duplicateId = (int) $canonical['id'];
                    $db->prepare('UPDATE projects SET code=? WHERE id=?')->execute(['gda-caixas-duplicate-' . $duplicateId, $duplicateId]);
                    $duplicateLinks = $db->prepare('SELECT * FROM client_projects WHERE project_id=? ORDER BY id');
                    $duplicateLinks->execute([$duplicateId]);
                    $findTarget = $db->prepare('SELECT * FROM client_projects WHERE client_id=? AND project_id=? FOR UPDATE');
                    $insertTarget = $db->prepare('INSERT INTO client_projects (client_id,project_id,status,activated_at,deactivated_at,settings,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
                    $updateTarget = $db->prepare("UPDATE client_projects SET status=?,activated_at=COALESCE(activated_at,?),settings=COALESCE(settings,?),deactivated_at=IF(?='active',NULL,deactivated_at) WHERE id=?");
                    foreach ($duplicateLinks->fetchAll() as $link) {
                        $findTarget->execute([$link['client_id'], $projectId]);
                        $target = $findTarget->fetch();
                        if ($target) {
                            $status = $target['status'] === 'active' || $link['status'] === 'active' ? 'active' : $target['status'];
                            $updateTarget->execute([$status,$link['activated_at'],$link['settings'],$status,$target['id']]);
                        } else {
                            $insertTarget->execute([$link['client_id'],$projectId,$link['status'],$link['activated_at'],$link['deactivated_at'],$link['settings'],$link['created_at'],$link['updated_at']]);
                        }
                    }
                    if (PHP_SAPI === 'cli' && getenv('GDA_TEST_MIGRATION_FAIL_AFTER_MERGE') === '1') {
                        throw new \RuntimeException('Falha controlada na consolidação.');
                    }
                    $db->prepare('DELETE FROM client_projects WHERE project_id=?')->execute([$duplicateId]);
                    $db->prepare('DELETE FROM projects WHERE id=?')->execute([$duplicateId]);
                }
            } elseif ($canonical) {
                $projectId = (int) $canonical['id'];
            }

            $values = ['Caixas','GDA Caixas','gda-caixas','Gerenciamento de caixas diários, semanais, quinzenais, mensais e personalizados.','wallet','1.0.0','https://caixas.gda.app.br','https://caixas.gda.app.br/admin-gda','Gestão financeira','deploying'];
            if ($projectId) {
                $stmt = $db->prepare('UPDATE projects SET name=?,full_name=?,code=?,description=?,icon=?,version=?,url=?,admin_url=?,category=?,status=? WHERE id=?');
                $stmt->execute([...$values, $projectId]);
            } else {
                $stmt = $db->prepare('INSERT INTO projects (name,full_name,code,description,icon,version,url,admin_url,category,status,display_order) VALUES (?,?,?,?,?,?,?,?,?,?,1)');
                $stmt->execute($values);
                $projectId = (int) $db->lastInsertId();
            }

            $setting = $db->prepare('INSERT INTO settings (setting_key,setting_value,type) VALUES (?,?,"string") ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
            $setting->execute(['caixas_url', 'https://caixas.gda.app.br']);
            $setting->execute(['caixas_admin_url', 'https://caixas.gda.app.br/admin-gda']);
            $db->prepare("DELETE FROM settings WHERE setting_key='caixa_diario_url'")->execute();
            $migration = $db->prepare("INSERT INTO migrations (migration_id,version,description,status,executed_at) VALUES (?,'1.0.0','Substitui Caixa Diário por GDA Caixas preservando vínculos.','executed',NOW()) ON DUPLICATE KEY UPDATE version='1.0.0',status='executed',executed_at=NOW()");
            $migration->execute([self::MIGRATION_ID]);
            $db->commit();
            Logger::activity('module_migrated', 'Módulo legado atualizado para Caixas.', 'success', null, ['project_id' => $projectId, 'code' => 'gda-caixas']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            @file_put_contents(base_path('storage/logs/error.log'), '[' . date('c') . '] Migração Caixas falhou: ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }

    private static function ensureProjectSchema(PDO $db): void
    {
        $columns = $db->query('SHOW COLUMNS FROM projects')->fetchAll(PDO::FETCH_COLUMN);
        $changes = ['full_name' => 'ADD COLUMN full_name VARCHAR(190) NULL AFTER name','admin_url' => 'ADD COLUMN admin_url VARCHAR(500) NULL AFTER url','category' => 'ADD COLUMN category VARCHAR(100) NULL AFTER admin_url'];
        foreach ($changes as $column => $sql) if (!in_array($column, $columns, true)) $db->exec('ALTER TABLE projects ' . $sql);
        $status = $db->query("SHOW COLUMNS FROM projects LIKE 'status'")->fetch();
        if ($status && !str_contains((string) $status['Type'], "'deploying'")) $db->exec("ALTER TABLE projects MODIFY status ENUM('active','inactive','deploying') NOT NULL DEFAULT 'active'");
    }
}
