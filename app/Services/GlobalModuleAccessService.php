<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class GlobalModuleAccessService
{
    public function __construct(private readonly PDO$db,private readonly ModuleProtocolClient$client,private readonly ProjectConnectionRepository$repository,private readonly PlatformIdentity$identity){}
    public function create(int$projectId,int$userId):array
    {
        $connection=$this->repository->connectionForProject($projectId);
        if(!$connection)throw new ModuleAttachmentException('module_not_attached','O módulo ainda não está anexado.');
        $moduleUrl=rtrim((string)$connection['endpoint_url'],'/');$integrated=($connection['integration_status']??'')==='integrado';
        if(!$integrated)return['mode'=>'local','action'=>$moduleUrl.'/admin-gda'];
        if(empty($connection['machine_credential_encrypted']))throw new ModuleAttachmentException('module_not_integrated','O vínculo do módulo não está ativo.');
        $credential=$this->identity->decrypt((string)$connection['machine_credential_encrypted']);$status=$this->client->get($moduleUrl.'/gda/integration/status',['Authorization'=>'Bearer '.$credential]);if(!hash_equals((string)$connection['module_instance_id'],(string)($status['module_uuid']??''))||($status['status']??'')!=='integrado')throw new ModuleAttachmentException('module_not_integrated','O vínculo do módulo não está ativo.');$this->repository->updateCommunication((string)$connection['module_instance_id'],'integrado');$signed=$this->identity->signGlobalAccess((string)$connection['module_instance_id']);
        $claims=$signed['claims'];
        $this->db->prepare('INSERT INTO module_access_issuances(module_uuid,jti_hash,issued_by_user_id,issued_at,expires_at)VALUES(?,?,?,?,?)')->execute([$connection['module_instance_id'],hash('sha256',(string)$claims['jti']),$userId,date('Y-m-d H:i:s',(int)$claims['iat']),date('Y-m-d H:i:s',(int)$claims['exp'])]);
        try{Logger::activity('module.global_access_issued','Acesso global federado emitido.','success',$userId,['module_uuid'=>$connection['module_instance_id'],'project_id'=>$projectId,'expires_at'=>$claims['exp']]);}catch(\Throwable){}
        return ['mode'=>'federated','action'=>$moduleUrl.'/admin-gda/auth/gda','access_token'=>$signed['token'],'expires_at'=>$claims['exp']];
    }

    public function consumeInitial(string $token):array
    {
        $claims=$this->identity->verifyGlobalAccess($token);$now=time();$moduleUuid=(string)($claims['aud']??'');$jti=(string)($claims['jti']??'');
        if(($claims['iss']??'')!==$this->identity->uuid()||($claims['role']??'')!=='global_admin'||($claims['purpose']??'')!=='initial_global_access'||$jti===''||(int)($claims['iat']??0)>$now+30||(int)($claims['exp']??0)<=$now||(int)$claims['exp']-(int)$claims['iat']>120)throw new ModuleAttachmentException('initial_access_invalid','Acesso inicial inválido ou expirado.');
        $connection=$this->repository->connectionByInstance($moduleUuid);if(!$connection||($connection['integration_status']??'')==='integrado')throw new ModuleAttachmentException('initial_access_unavailable','Acesso inicial indisponível.');
        $this->db->beginTransaction();try{$delete=$this->db->prepare('DELETE FROM module_access_issuances WHERE module_uuid=? AND jti_hash=? AND expires_at>?');$delete->execute([$moduleUuid,hash('sha256',$jti),date('Y-m-d H:i:s',$now)]);if($delete->rowCount()!==1)throw new ModuleAttachmentException('initial_access_replayed','Acesso inicial já utilizado.');$this->db->commit();}catch(\Throwable$e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
        return ['accepted'=>true,'claims'=>$claims];
    }
}
