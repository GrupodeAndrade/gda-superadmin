<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

use Gda\Modules\Caixas\Repositories\CycleRepository;
use PDO;
use RuntimeException;

final class CycleManagementService
{
    public function __construct(private PDO $db,private CycleRepository $cycles){}
    public function canDeleteCycle(int $id): bool
    {
        $counts=$this->cycles->dependencyCounts($id);if(!$counts)return false;
        foreach(['contributions','payouts','receipts','messages','real_participants','operational_points']as$key)if(($counts[$key]??1)>0)return false;
        return true;
    }
    public function archiveCycle(int $id): bool{return$this->cycles->setStatus($id,'active','archived');}
    public function reactivateCycle(int $id): bool{return$this->cycles->setStatus($id,'archived','active');}
    public function deleteEmptyCycle(int $id): ?int
    {
        $cycle=$this->cycles->findOwned($id);if(!$cycle)throw new RuntimeException('not_found');
        if(!$this->canDeleteCycle($id))throw new RuntimeException('has_history');
        $counts=$this->cycles->dependencyCounts($id);$adminId=(int)($counts['admin_id']??0);$next=$this->cycles->nextActiveId($id);
        $this->db->beginTransaction();
        try{
            $this->db->prepare('DELETE FROM message_templates WHERE cycle_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM cycle_points WHERE cycle_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM cycle_participants WHERE cycle_id=?')->execute([$id]);
            $stmt=$this->db->prepare('DELETE FROM cycles WHERE id=? AND company_id=?');$stmt->execute([$id,(int)$cycle['company_id']]);if($stmt->rowCount()!==1)throw new RuntimeException('delete_failed');
            if($adminId>0){$shared=$this->db->prepare('SELECT COUNT(*) FROM cycle_participants WHERE participant_id=?');$shared->execute([$adminId]);$moves=$this->db->prepare('SELECT (SELECT COUNT(*) FROM contributions WHERE participant_id=?)+(SELECT COUNT(*) FROM payouts WHERE participant_id=?)+(SELECT COUNT(*) FROM payment_receipts WHERE participant_id=?)');$moves->execute([$adminId,$adminId,$adminId]);if((int)$shared->fetchColumn()===0&&(int)$moves->fetchColumn()===0)$this->db->prepare("DELETE FROM participants WHERE id=? AND company_id=? AND name='Administração'")->execute([$adminId,(int)$cycle['company_id']]);}
            $this->db->commit();return$next;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }
}
