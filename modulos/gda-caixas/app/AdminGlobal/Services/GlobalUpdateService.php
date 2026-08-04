<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Services;
use Gda\AdminGlobal\Repositories\GlobalAuditRepository;

final class GlobalUpdateService{
 public function __construct(private GlobalAuditRepository $audit){}
 public function history():array{return recent_app_updates();}
 public function install(array $file,int $userId):array{$this->audit->record('global_update_started',$userId,'update',null,['result'=>'started']);if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \RuntimeException('Pacote não recebido.');$name=basename((string)$file['name']);if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='zip')throw new \RuntimeException('Envie um pacote ZIP.');$tmp=update_storage_root().'/upload-'.bin2hex(random_bytes(12)).'.zip';if(!move_uploaded_file($file['tmp_name'],$tmp))throw new \RuntimeException('Falha ao armazenar pacote temporário.');try{$result=install_update_package($tmp,$name);$this->audit->record('global_update_succeeded',$userId,'update',null,['result'=>'success','version'=>$result['final_version']??null]);return$result;}catch(\Throwable $e){$this->audit->record('global_update_failed',$userId,'update',null,['result'=>'failed']);throw$e;}finally{if(is_file($tmp))unlink($tmp);}}
}
