<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Repositories;

use Gda\ClientArea\ClientContext;
use PDO;

final class ContributionRepository
{
    public function __construct(private PDO $db, private ClientContext $context) {}
    public function find(int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT c.* FROM contributions c INNER JOIN cycles cy ON cy.id=c.cycle_id WHERE c.id=? AND cy.company_id=?');
        $stmt->execute([$id,$this->context->companyId()]);
        return $stmt->fetch() ?: null;
    }
    public function delete(int $id): bool
    {
        $stmt=$this->db->prepare('DELETE FROM contributions WHERE id=? AND cycle_id IN (SELECT id FROM cycles WHERE company_id=?)');
        $stmt->execute([$id,$this->context->companyId()]);
        return $stmt->rowCount() === 1;
    }
}
