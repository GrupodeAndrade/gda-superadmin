<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Repositories;

use Gda\ClientArea\ClientContext;
use PDO;

final class ParticipantRepository
{
    public function __construct(private PDO $db, private ClientContext $context) {}
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM participants WHERE id=? AND company_id=?');
        $stmt->execute([$id, $this->context->companyId()]);
        return $stmt->fetch() ?: null;
    }
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO participants(company_id,name,phone,email,pix_type,pix_key,notes,access_token) VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([$this->context->companyId(),$data['name'],$data['phone'],$data['email'],$data['pix_type'],$data['pix_key'],$data['notes'],$data['access_token']]);
        return (int)$this->db->lastInsertId();
    }
}
