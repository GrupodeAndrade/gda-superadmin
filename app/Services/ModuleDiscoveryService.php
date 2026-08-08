<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class ModuleDiscoveryService
{
    public function __construct(
        private readonly PDO $db,
        private readonly ModuleProtocolClient $client,
        private readonly ModuleEndpointPolicy $policy,
        private readonly ProjectConnectionRepository $repository,
    ) {}

    public function attach(string $url, int $userId, string $platformVersion): array
    {
        try {
            $baseUrl = $this->baseUrl($url);
            $response = $this->client->get($baseUrl . '/gda/module');
            $module = $this->normalize($response, $baseUrl, $platformVersion);
            $this->db->beginTransaction();
            $saved = $this->repository->saveDiscovered($module, $userId);
            try{Logger::activity('project.attached_by_url', 'Módulo anexado por descoberta pública.', 'success', $userId, ['project_id'=>$saved['project_id'],'module_uuid'=>$module['uuid'],'module_code'=>$module['code']]);}catch(Throwable){}
            $this->db->commit();
            return $saved + ['module'=>$module];
        } catch (ModuleAttachmentException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->safeFailure($userId, $e->errorCode);
            throw $e;
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->safeFailure($userId, 'internal_error');
            throw new ModuleAttachmentException('internal_error', 'Não foi possível anexar o módulo.');
        }
    }

    public function normalize(array $source, string $requestedBaseUrl, string $platformVersion): array
    {
        if (array_key_exists('ok', $source) && $source['ok'] !== true) $this->invalid();
        if (isset($source['module']) && is_array($source['module'])) $source = $source['module'];
        foreach (['uuid','code','name','slug','description','version','api_version','module_url','integration_status','minimum_gda_version'] as $field) {
            if (!is_string($source[$field] ?? null) || trim($source[$field]) === '') $this->invalid();
        }
        $uuid=trim($source['uuid']);$code=trim($source['code']);$slug=trim($source['slug']);$moduleUrl=rtrim(trim($source['module_url']),'/');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$uuid)) $this->invalid();
        if (!preg_match('/^GDA-[A-Z0-9]+(?:-[A-Z0-9]+)*$/',$code) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)) $this->invalid();
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',$source['version']) || !preg_match('/^\d+\.\d+$/',$source['api_version']) || !preg_match('/^\d+\.\d+\.\d+$/',$source['minimum_gda_version'])) $this->invalid();
        $moduleUrl=$this->policy->assertAllowed($moduleUrl);
        if (!hash_equals(strtolower($requestedBaseUrl), strtolower($moduleUrl))) throw new ModuleAttachmentException('module_url_mismatch','A identidade retornada não corresponde à URL informada.');
        $capabilities=$source['capabilities']??null;$compatibility=$source['compatible_gda_versions']??null;$health=$source['health']??null;
        if (!is_array($capabilities)||!array_is_list($capabilities)||!is_array($compatibility)||!array_is_list($compatibility)||!is_array($health)) $this->invalid();
        foreach($capabilities as $item)if(!is_string($item)||trim($item)==='')$this->invalid();
        foreach($compatibility as $item)if(!is_string($item)||trim($item)==='')$this->invalid();
        if (!is_string($health['level']??null)||!is_string($health['summary']??null)) $this->invalid();
        if (strtoupper($health['level'])==='ERROR') throw new ModuleAttachmentException('module_offline','O módulo está indisponível.');
        if (version_compare($platformVersion,$source['minimum_gda_version'],'<') || !$this->compatible($platformVersion,$compatibility)) throw new ModuleAttachmentException('incompatible_version','Versão incompatível.');
        if (mb_strlen($source['name'])>150||mb_strlen($source['description'])>2000||strlen($moduleUrl)>480) $this->invalid();
        return [
            'uuid'=>$uuid,'code'=>$code,'project_code'=>strtolower($code),'name'=>trim($source['name']),'full_name'=>trim($source['name']),
            'slug'=>$slug,'description'=>trim($source['description']),'type'=>trim((string)($source['type']??'module')),
            'version'=>$source['version'],'api_version'=>$source['api_version'],'url'=>$moduleUrl,'admin_url'=>$moduleUrl.'/admin-gda',
            'status'=>trim($source['integration_status']),'connection_status'=>trim((string)($source['connection_status']??'nao_integrado')),
            'minimum_version'=>$source['minimum_gda_version'],'compatibility'=>$compatibility,'capabilities'=>$capabilities,'health'=>$health,
            'icon'=>$slug==='caixas'?'wallet':'grid','category'=>'Módulo GDA',
        ];
    }

    private function baseUrl(string $url): string
    {
        $url=rtrim(trim($url),'/');$parts=parse_url($url);
        if($url===''||!is_array($parts)||isset($parts['query'])||isset($parts['fragment'])||($parts['path']??'')!=='')throw new ModuleAttachmentException('invalid_url','URL inválida.');
        return $this->policy->assertAllowed($url);
    }
    private function compatible(string $version,array $ranges):bool{foreach($ranges as $range){if($range===$version)return true;if(preg_match('/^(\d+)\.x$/',$range,$m)&&str_starts_with($version,$m[1].'.'))return true;}return false;}
    private function invalid():never{throw new ModuleAttachmentException('invalid_response','Resposta inválida do módulo.');}
    private function safeFailure(int $userId,string $code):void{try{Logger::activity('project.attachment_failed','Falha ao anexar módulo por URL.','failure',$userId,['error_code'=>$code]);}catch(Throwable){}}
}
