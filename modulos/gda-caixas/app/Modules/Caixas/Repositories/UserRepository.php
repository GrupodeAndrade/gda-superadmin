<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db) {}
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT u.* FROM users u LEFT JOIN companies c ON c.id=u.company_id WHERE u.email=? AND u.active=1 AND c.status='active' AND u.role IN('client_admin','operator') LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
}
