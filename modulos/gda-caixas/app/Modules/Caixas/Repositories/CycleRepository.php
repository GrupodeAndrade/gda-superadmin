<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Repositories;

use Gda\ClientArea\ClientContext;
use PDO;

final class CycleRepository
{
    public function __construct(private PDO $db, private ClientContext $context) {}
    public function active(?int $selectedId = null): ?array
    {
        if ($selectedId) {
            $stmt = $this->db->prepare("SELECT * FROM cycles WHERE id=? AND company_id=? AND status IN('active','archived','closed') LIMIT 1");
            $stmt->execute([$selectedId, $this->context->companyId()]);
            if ($cycle = $stmt->fetch()) return $cycle;
        }
        $stmt = $this->db->prepare("SELECT * FROM cycles WHERE company_id=? AND status='active' ORDER BY created_at DESC,id DESC LIMIT 1");
        $stmt->execute([$this->context->companyId()]);
        return $stmt->fetch() ?: null;
    }
    public function findOwned(int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM cycles WHERE id=? AND company_id=? LIMIT 1');
        $stmt->execute([$id,$this->context->companyId()]);
        return $stmt->fetch()?:null;
    }
    public function dependencyCounts(int $id): array
    {
        $cycle=$this->findOwned($id);if(!$cycle)return[];
        $admin=$this->db->prepare('SELECT participant_id FROM cycle_points WHERE cycle_id=? AND day=1 LIMIT 1');$admin->execute([$id]);$adminId=(int)$admin->fetchColumn();
        $queries=[
            'contributions'=>'SELECT COUNT(*) FROM contributions WHERE cycle_id=?',
            'payouts'=>'SELECT COUNT(*) FROM payouts WHERE cycle_id=?',
            'receipts'=>'SELECT COUNT(*) FROM payment_receipts WHERE cycle_id=?',
            'messages'=>'SELECT COUNT(*) FROM message_queue WHERE cycle_id=?',
            'real_participants'=>'SELECT COUNT(*) FROM cycle_participants WHERE cycle_id=? AND participant_id<>?',
            'operational_points'=>'SELECT COUNT(*) FROM cycle_points WHERE cycle_id=? AND NOT(day=1 AND participant_id=?) AND participant_id IS NOT NULL',
        ];
        $counts=[];foreach($queries as$key=>$sql){$stmt=$this->db->prepare($sql);$args=in_array($key,['real_participants','operational_points'],true)?[$id,$adminId]:[$id];$stmt->execute($args);$counts[$key]=(int)$stmt->fetchColumn();}
        return $counts+['admin_id'=>$adminId];
    }
    public function setStatus(int $id,string $from,string $to): bool
    {
        $stmt=$this->db->prepare('UPDATE cycles SET status=? WHERE id=? AND company_id=? AND status=?');$stmt->execute([$to,$id,$this->context->companyId(),$from]);return$stmt->rowCount()===1;
    }
    public function nextActiveId(int $excludingId): ?int
    {
        $stmt=$this->db->prepare("SELECT id FROM cycles WHERE company_id=? AND status='active' AND id<>? ORDER BY created_at DESC,id DESC LIMIT 1");$stmt->execute([$this->context->companyId(),$excludingId]);$id=$stmt->fetchColumn();return$id===false?null:(int)$id;
    }
}
