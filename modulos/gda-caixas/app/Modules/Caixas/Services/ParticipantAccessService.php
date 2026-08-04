<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

use PDO;
use RuntimeException;

final class ParticipantAccessService
{
    public function __construct(private PDO $db) {}

    public function generate(int $companyId,int $cycleId,int $participantId): string
    {
        $owned=$this->ownedLink($companyId,$cycleId,$participantId);
        if(!$owned)throw new RuntimeException('Vínculo participante-caixa não encontrado.');
        do{$token=$this->randomToken();$hash=hash('sha256',$token);$check=$this->db->prepare('SELECT COUNT(*) FROM cycle_participants WHERE access_token_hash=?');$check->execute([$hash]);}while((int)$check->fetchColumn()>0);
        $rotating=!empty($owned['access_token_hash']);
        $stmt=$this->db->prepare('UPDATE cycle_participants SET access_token_hash=?,access_token_prefix=?,access_enabled=1,access_created_at=COALESCE(access_created_at,NOW()),access_rotated_at=IF(?,NOW(),access_rotated_at),last_access_at=NULL WHERE cycle_id=? AND participant_id=?');
        $stmt->execute([$hash,substr($token,0,10),$rotating?1:0,$cycleId,$participantId]);
        return $token;
    }

    public function setEnabled(int $companyId,int $cycleId,int $participantId,bool $enabled): void
    {
        $link=$this->ownedLink($companyId,$cycleId,$participantId);
        if(!$link||empty($link['access_token_hash']))throw new RuntimeException('Gere um link antes de alterar o acesso.');
        $stmt=$this->db->prepare('UPDATE cycle_participants SET access_enabled=? WHERE cycle_id=? AND participant_id=?');$stmt->execute([$enabled?1:0,$cycleId,$participantId]);
    }

    public function ownedLink(int $companyId,int $cycleId,int $participantId): ?array
    {
        $stmt=$this->db->prepare('SELECT cp.*,p.name,p.phone,c.name cycle_name,c.status cycle_status FROM cycle_participants cp JOIN participants p ON p.id=cp.participant_id AND p.company_id=? JOIN cycles c ON c.id=cp.cycle_id AND c.company_id=? WHERE cp.cycle_id=? AND cp.participant_id=? LIMIT 1');
        $stmt->execute([$companyId,$companyId,$cycleId,$participantId]);return$stmt->fetch()?:null;
    }

    public function resolve(string $token,bool $touch=true): ?array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{43}$/',$token))return null;
        $hash=hash('sha256',$token);
        $stmt=$this->db->prepare("SELECT cp.*,p.id participant_id,p.company_id,p.name participant_name,p.phone,p.email,p.pix_type participant_pix_type,p.pix_key participant_pix_key,p.active participant_active,c.name cycle_name,c.modality,c.start_date,c.year,c.month,c.daily_amount,c.admin_pays,c.status cycle_status,co.name company_name,co.status company_status FROM cycle_participants cp JOIN participants p ON p.id=cp.participant_id JOIN cycles c ON c.id=cp.cycle_id AND c.company_id=p.company_id JOIN companies co ON co.id=c.company_id WHERE cp.access_token_hash=? AND cp.access_enabled=1 AND p.active=1 AND co.status='active' AND c.status IN('active','archived') LIMIT 1");
        $stmt->execute([$hash]);$link=$stmt->fetch();
        if(!$link||!hash_equals((string)$link['access_token_hash'],$hash))return null;
        if($touch){$update=$this->db->prepare('UPDATE cycle_participants SET last_access_at=NOW() WHERE cycle_id=? AND participant_id=? AND access_token_hash=?');$update->execute([$link['cycle_id'],$link['participant_id'],$hash]);}
        return$link;
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
    }
}
