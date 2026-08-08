<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\{Database,ModuleAttachmentException,ModuleEndpointPolicy,ModulePairingService,ModuleProtocolClient,PlatformIdentity,ProjectConnectionRepository};

final class ModulePairingController
{
    public function receive():void
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
        $production=strtolower((string)(getenv('APP_ENV')?:'production'))==='production';$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';if($production&&!$secure){$this->json(['accepted'=>false,'error'=>'https_required'],400);return;}
        if(!str_contains(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')){$this->json(['accepted'=>false,'error'=>'invalid_content_type'],415);return;}
        try{$raw=(string)file_get_contents('php://input',false,null,0,16385);if(strlen($raw)>16384){$this->json(['accepted'=>false,'error'=>'payload_too_large'],413);return;}$decoded=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($decoded))throw new \JsonException();$config=require base_path('config/app.php');$policy=new ModuleEndpointPolicy($config['module_attachment']['allowed_domain']);$db=Database::connection();$service=new ModulePairingService($db,new ModuleProtocolClient($policy,$config['module_attachment']['timeout'],$config['module_attachment']['max_response_bytes']),$policy,new ProjectConnectionRepository($db),PlatformIdentity::load($db));$this->json($service->receive($decoded),200);}
        catch(\JsonException){$this->json(['accepted'=>false,'error'=>'invalid_json'],400);}
        catch(ModuleAttachmentException$e){$status=in_array($e->errorCode,['invalid_pairing','unknown_module'],true)?422:($e->errorCode==='pairing_replayed'?409:503);$this->json(['accepted'=>false,'error'=>$e->errorCode],$status);}
        catch(\Throwable){$this->json(['accepted'=>false,'error'=>'integration_unavailable'],503);}
    }
    private function json(array$payload,int$status):void{http_response_code($status);echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
}
