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
        if(!$connection||($connection['integration_status']??'')!=='integrado'||empty($connection['machine_credential_encrypted']))throw new ModuleAttachmentException('module_not_integrated','O módulo ainda não está integrado.');
        $credential=$this->identity->decrypt((string)$connection['machine_credential_encrypted']);$moduleUrl=rtrim((string)$connection['endpoint_url'],'/');
        $status=$this->client->get($moduleUrl.'/gda/integration/status',['Authorization'=>'Bearer '.$credential]);
        if(!hash_equals((string)$connection['module_instance_id'],(string)($status['module_uuid']??''))||($status['status']??'')!=='integrado')throw new ModuleAttachmentException('module_not_integrated','O vínculo do módulo não está ativo.');
        $this->repository->updateCommunication((string)$connection['module_instance_id'],'integrado');$signed=$this->identity->signGlobalAccess((string)$connection['module_instance_id']);$claims=$signed['claims'];
        $this->db->prepare('INSERT INTO module_access_issuances(module_uuid,jti_hash,issued_by_user_id,issued_at,expires_at)VALUES(?,?,?,?,?)')->execute([$connection['module_instance_id'],hash('sha256',(string)$claims['jti']),$userId,date('Y-m-d H:i:s',(int)$claims['iat']),date('Y-m-d H:i:s',(int)$claims['exp'])]);
        try{Logger::activity('module.global_access_issued','Acesso global federado emitido.','success',$userId,['module_uuid'=>$connection['module_instance_id'],'project_id'=>$projectId,'expires_at'=>$claims['exp']]);}catch(\Throwable){}
        return ['action'=>$moduleUrl.'/admin-gda/auth/gda','access_token'=>$signed['token'],'expires_at'=>$claims['exp']];
    }
}
