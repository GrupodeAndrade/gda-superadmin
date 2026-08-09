<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{Database,GlobalModuleAccessService,ModuleAttachmentException,ModuleEndpointPolicy,ModuleProtocolClient,PlatformIdentity,ProjectConnectionRepository};

final class ModuleAccessController
{
    public function consume():void
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        try{$raw=(string)file_get_contents('php://input',false,null,0,16385);$input=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($input)||strlen((string)($input['access_token']??''))>8192)throw new \JsonException();$config=require base_path('config/app.php');$policy=new ModuleEndpointPolicy($config['module_attachment']['allowed_domain']);$db=Database::connection();$service=new GlobalModuleAccessService($db,new ModuleProtocolClient($policy,$config['module_attachment']['timeout'],$config['module_attachment']['max_response_bytes']),new ProjectConnectionRepository($db),PlatformIdentity::load($db));$this->json($service->consumeInitial((string)($input['access_token']??'')),200);}
        catch(\JsonException){$this->json(['accepted'=>false,'error'=>'invalid_request'],400);}catch(ModuleAttachmentException$e){$this->json(['accepted'=>false,'error'=>$e->errorCode],401);}catch(\Throwable){$this->json(['accepted'=>false,'error'=>'initial_access_unavailable'],503);}
    }
    private function json(array$payload,int$status):void{http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
