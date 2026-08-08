<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class ModulePairingService
{
    public function __construct(
        private readonly PDO $db,
        private readonly ModuleProtocolClient $client,
        private readonly ModuleEndpointPolicy $policy,
        private readonly ProjectConnectionRepository $repository,
        private readonly PlatformIdentity $identity,
    ) {}

    public function receive(array $input): array
    {
        $moduleUuid=trim((string)($input['module_uuid']??''));$moduleUrl=rtrim(trim((string)($input['module_url']??'')),'/');$requestId=trim((string)($input['request_id']??''));$token=trim((string)($input['pairing_token']??''));
        if(!$this->validUuid($moduleUuid)||!$this->validUuid($requestId)||strlen($token)<32||strlen($token)>200)throw new ModuleAttachmentException('invalid_pairing','Solicitação de integração inválida.');
        $moduleUrl=$this->policy->assertAllowed($moduleUrl);$connection=$this->repository->connectionByInstance($moduleUuid);
        if(!$connection||!hash_equals(strtolower(rtrim((string)$connection['endpoint_url'],'/')),strtolower($moduleUrl)))throw new ModuleAttachmentException('unknown_module','Módulo não reconhecido.');
        $this->identity->assertConfigured();$tokenHash=hash('sha256',$token);$this->beginReceipt($requestId,$moduleUuid,$moduleUrl,$tokenHash);
        $payload=['request_id'=>$requestId,'pairing_token'=>$token,'superadmin_uuid'=>$this->identity->uuid(),'superadmin_url'=>$this->identity->publicUrl(),'protocol_version'=>$this->identity->protocolVersion()];
        try{
            $validation=$this->client->post($moduleUrl.'/gda/integration/pair/validate',$payload);
            if(($validation['valid']??null)!==true||!is_array($validation['module']??null)||!hash_equals($moduleUuid,(string)($validation['module']['uuid']??'')))throw new ModuleAttachmentException('pairing_rejected','O módulo recusou a integração.');
            $this->db->prepare("UPDATE module_pairing_receipts SET status='validated',validated_at=?,error_code=NULL WHERE request_id=?")->execute([$this->now(),$requestId]);
            $confirmation=$this->client->post($moduleUrl.'/gda/integration/pair/confirm',$payload+['public_signing_key'=>$this->identity->publicKey(),'installation_id'=>$this->identity->installationId()]);
            $credential=$confirmation['module_credential']??null;
            if(($confirmation['confirmed']??null)!==true||($confirmation['status']??'')!=='integrado'||!is_string($credential)||strlen($credential)<32)throw new ModuleAttachmentException('pairing_rejected','O módulo recusou a confirmação.');
            $encrypted=$this->identity->encrypt($credential);
            $this->db->beginTransaction();$saved=$this->repository->markIntegrated($moduleUuid,$encrypted,$requestId);$this->db->prepare("UPDATE module_pairing_receipts SET status='confirmed',confirmed_at=?,error_code=NULL WHERE request_id=?")->execute([$this->now(),$requestId]);try{Logger::activity('module.integration_confirmed','Integração permanente do módulo confirmada.','success',null,['module_uuid'=>$moduleUuid,'project_id'=>(int)$saved['project_id'],'request_id'=>$requestId]);}catch(Throwable){}$this->db->commit();
            return ['accepted'=>true,'status'=>'integrado','module_uuid'=>$moduleUuid];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();$code=$e instanceof ModuleAttachmentException?$e->errorCode:'integration_unavailable';$this->db->prepare("UPDATE module_pairing_receipts SET status='failed',error_code=? WHERE request_id=? AND status<>'confirmed'")->execute([$code,$requestId]);try{Logger::activity('module.integration_failed','Falha na integração permanente do módulo.','failure',null,['module_uuid'=>$moduleUuid,'request_id'=>$requestId,'error_code'=>$code]);}catch(Throwable){}if($e instanceof ModuleAttachmentException)throw$e;throw new ModuleAttachmentException('integration_unavailable','Integração temporariamente indisponível.');}
    }

    private function beginReceipt(string $requestId,string $moduleUuid,string $moduleUrl,string $tokenHash):void
    {
        $stmt=$this->db->prepare('SELECT * FROM module_pairing_receipts WHERE request_id=?');$stmt->execute([$requestId]);$current=$stmt->fetch();
        if($current){if(!hash_equals((string)$current['module_uuid'],$moduleUuid)||!hash_equals((string)$current['pairing_token_hash'],$tokenHash)||$current['status']==='confirmed')throw new ModuleAttachmentException('pairing_replayed','Solicitação de integração já utilizada.');$this->db->prepare("UPDATE module_pairing_receipts SET status='processing',error_code=NULL WHERE request_id=?")->execute([$requestId]);return;}
        $this->db->prepare("INSERT INTO module_pairing_receipts(request_id,module_uuid,pairing_token_hash,module_url,status,received_at)VALUES(?,?,?,?,'processing',?)")->execute([$requestId,$moduleUuid,$tokenHash,$moduleUrl,$this->now()]);
    }
    private function validUuid(string$value):bool{return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$value)===1;}
    private function now():string{return date('Y-m-d H:i:s');}
}
