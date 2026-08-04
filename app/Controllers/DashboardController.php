<?php
declare(strict_types=1);
namespace App\Controllers;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $stats = $this->db->query("SELECT
          (SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL) total_clients,
          (SELECT COUNT(*) FROM clients WHERE status='active' AND deleted_at IS NULL) active_clients,
          (SELECT COUNT(*) FROM clients WHERE status='inactive' AND deleted_at IS NULL) inactive_clients,
          (SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL) total_projects,
          (SELECT COUNT(*) FROM projects WHERE status='active' AND deleted_at IS NULL) active_projects,
          (SELECT COUNT(*) FROM client_projects WHERE status='active') active_links")->fetch();
        $clients = $this->db->query('SELECT * FROM clients WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5')->fetchAll();
        $logs = $this->db->query('SELECT l.*,u.name user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
        $caixas = $this->db->query("SELECT * FROM projects WHERE code='gda-caixas' AND deleted_at IS NULL LIMIT 1")->fetch() ?: null;
        $version = json_decode((string) file_get_contents(base_path('version.json')), true);
        $backups = glob(base_path('storage/backups/*.zip')) ?: [];
        $lastBackup = $backups ? date('d/m/Y H:i', max(array_map('filemtime', $backups))) : 'Nenhum backup';
        $folders = [];
        foreach (['storage/backups','storage/cache','storage/logs','storage/temp','storage/updates','config'] as $folder) $folders[$folder] = is_writable(base_path($folder));
        $this->view('dashboard/index', compact('stats','clients','logs','caixas','version','lastBackup','folders'));
    }
}
