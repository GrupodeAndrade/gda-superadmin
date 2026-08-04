<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Database;
use App\Services\View;
use PDO;

abstract class BaseController
{
    protected PDO $db;
    public function __construct() { $this->db = Database::connection(); }
    protected function view(string $view, array $data = [], string $layout = 'admin'): void { View::render($view, $data, $layout); }
    protected function input(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
    protected function find(string $table, int $id): array
    {
        $allowed = ['clients','projects'];
        if (!in_array($table, $allowed, true)) throw new \InvalidArgumentException();
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); View::render('errors/404'); exit; }
        return $row;
    }
}

