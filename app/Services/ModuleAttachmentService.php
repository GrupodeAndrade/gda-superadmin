<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class ModuleAttachmentService
{
    public function __construct(
        private readonly PDO $db,
        private readonly ModuleAttachmentClient $client,
        private readonly ModuleEndpointPolicy $policy,
        private readonly ProjectConnectionRepository $repository,
        private readonly ModuleAttachmentRateLimiter $limiter,
    ) {}

    public function validate(string $url, string $key, int $userId, string $ip, string $platformVersion): array
    {
        $this->limiter->assertAllowed($userId, $ip);
        try {
            $baseUrl = $this->validateBaseUrl($url);
            $attachmentKey = $this->validateKey($key);
            $response = $this->client->request($baseUrl . '/gda/integration/attach', ['attachment_key' => $attachmentKey]);
            $module = $this->normalizeModule($response, $platformVersion);
            $this->assertNotAttached($module);
            $this->limiter->clear($userId, $ip);
            $this->safeAudit('project.attachment_validated', 'Módulo validado para anexação.', 'success', $userId, ['module_uuid' => $module['uuid'], 'module_type' => $module['type']]);
            return ['url' => $baseUrl, 'key' => $attachmentKey, 'module' => $module, 'validated_at' => time(), 'user_id' => $userId];
        } catch (ModuleAttachmentException $e) {
            $this->recordFailure($userId, $ip, $e->errorCode);
            throw $e;
        } catch (Throwable) {
            $this->recordFailure($userId, $ip, 'internal_error');
            throw new ModuleAttachmentException('internal_error', 'Não foi possível validar o módulo.');
        }
    }

    public function confirm(array $pending, int $userId, string $ip, string $platformVersion): array
    {
        if (($pending['user_id'] ?? 0) !== $userId || time() - (int) ($pending['validated_at'] ?? 0) > 600) {
            throw new ModuleAttachmentException('validation_expired', 'A validação expirou. Valide o módulo novamente.');
        }
        $this->limiter->assertAllowed($userId, $ip);
        try {
            $baseUrl = $this->validateBaseUrl((string) ($pending['url'] ?? ''));
            $key = $this->validateKey((string) ($pending['key'] ?? ''));
            $validated = $pending['module'] ?? null;
            if (!is_array($validated)) $this->invalidResponse();
            $response = $this->client->request($baseUrl . '/gda/integration/attach/confirm', ['attachment_key' => $key]);
            $module = $this->confirmationModule($response, $validated, $platformVersion);
            if (!hash_equals((string) $validated['uuid'], (string) $module['uuid'])) $this->invalidResponse();
            $this->assertNotAttached($module);
            $this->db->beginTransaction();
            $saved = $this->repository->saveOfficial($module, $baseUrl, $userId);
            $this->safeAudit('project.attached', 'Módulo anexado à Plataforma.', 'success', $userId, ['project_id' => $saved['project_id'], 'module_uuid' => $module['uuid'], 'module_type' => $module['type']]);
            $this->db->commit();
            $this->limiter->clear($userId, $ip);
            return $saved + ['module' => $module];
        } catch (ModuleAttachmentException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->recordFailure($userId, $ip, $e->errorCode);
            throw $e;
        } catch (Throwable) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->recordFailure($userId, $ip, 'internal_error');
            throw new ModuleAttachmentException('internal_error', 'Não foi possível anexar o módulo.');
        }
    }

    private function validateBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $parts = parse_url($url);
        if ($url === '' || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['query']) || (($parts['path'] ?? '') !== '')) {
            throw new ModuleAttachmentException('invalid_url', 'URL inválida.');
        }
        try { return $this->policy->assertAllowed($url); }
        catch (ModuleAttachmentException) { throw new ModuleAttachmentException('invalid_url', 'URL inválida.'); }
    }

    private function validateKey(string $key): string
    {
        $key = strtoupper(trim($key));
        if (!preg_match('/^[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}$/', $key)) {
            throw new ModuleAttachmentException('invalid_key', 'Chave inválida.');
        }
        return $key;
    }

    private function normalizeModule(array $response, string $platformVersion): array
    {
        if (($response['ok'] ?? null) !== true || ($response['attachment_state'] ?? '') !== 'anexacao_pendente') $this->invalidResponse();
        $source = $response['module'] ?? null;
        if (!is_array($source)) $this->invalidResponse();
        $uuid = $source['uuid'] ?? null;$code=$source['code']??null;$slug=$source['slug']??null;$type='module';$url=$source['module_url']??null;$status=$source['integration_status']??null;$apiVersion=$source['api_version']??null;
        foreach (['name','description','version'] as $field) if (!is_string($source[$field] ?? null) || trim($source[$field]) === '') $this->invalidResponse();
        if (!is_string($uuid) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,189}$/', $uuid)) $this->invalidResponse();
        if(!is_string($code)||!preg_match('/^GDA-[A-Z0-9]+(?:-[A-Z0-9]+)*$/',$code)||!is_string($slug)||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||!is_string($status)||trim($status)===''||!is_string($apiVersion)||!preg_match('/^\d+\.\d+$/',$apiVersion))$this->invalidResponse();
        if (!is_string($url)) $this->invalidResponse();
        $url = $this->policy->assertAllowed(rtrim($url, '/'));
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $source['version'])) $this->invalidResponse();
        $fullName=trim($source['name']);$description=trim($source['description']);$adminUrl=$url;$icon=$slug==='caixas'?'wallet':'grid';$category='Módulo GDA';
        if(mb_strlen($source['name'])>150||mb_strlen($description)>2000||mb_strlen($code)>100||mb_strlen($slug)>100||mb_strlen($type)>100||mb_strlen($status)>50||strlen($url)>480)$this->invalidResponse();
        $capabilities=$source['capabilities']??null;$compatibility=$source['compatible_gda_versions']??null;$health=$source['health']??null;$minimum=$source['minimum_gda_version']??null;
        if(!is_array($capabilities)||!array_is_list($capabilities)||!is_array($compatibility)||!array_is_list($compatibility)||!is_array($health)||!is_string($minimum)||!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',$minimum))$this->invalidResponse();
        foreach($capabilities as $capability)if(!is_string($capability)||trim($capability)==='')$this->invalidResponse();foreach($compatibility as $range)if(!is_string($range)||trim($range)==='')$this->invalidResponse();
        if(version_compare($platformVersion,$minimum,'<')||!$this->matchesCompatibility($platformVersion,$compatibility))throw new ModuleAttachmentException('incompatible_version','Versão incompatível.');
        if(strtoupper((string)($health['level']??''))==='ERROR')throw new ModuleAttachmentException('module_offline','Módulo indisponível.');
        if(!isset($health['level'],$health['summary'])||!is_string($health['level'])||!is_string($health['summary']))$this->invalidResponse();
        return ['uuid'=>$uuid,'code'=>$code,'slug'=>$slug,'project_code'=>strtolower($code),'name'=>trim($source['name']),'full_name'=>$fullName,'description'=>$description,'version'=>$source['version'],'api_version'=>$apiVersion,'url'=>$url,'admin_url'=>$adminUrl,'type'=>trim($type),'status'=>trim($status),'compatibility'=>$compatibility,'capabilities'=>$capabilities,'health'=>$health,'minimum_version'=>$minimum,'last_updated_at'=>null,'icon'=>$icon,'category'=>$category];
    }

    private function confirmationModule(array $response, array $validated, string $platformVersion): array
    {
        if(($response['ok']??null)!==true||($response['attachment_state']??'')!=='anexado'||!is_string($response['module_uuid']??null)||!hash_equals((string)$validated['uuid'],$response['module_uuid']))$this->invalidResponse();
        return $validated;
    }

    private function assertNotAttached(array $module): void
    {
        if ($this->repository->connectionByInstance($module['uuid'])) throw new ModuleAttachmentException('duplicate_uuid', 'Este módulo já está anexado.');
        $project = $this->repository->projectByCode($module['project_code']);
        if ($project && $this->repository->connectionForProject((int) $project['id'])) throw new ModuleAttachmentException('module_attached', 'Este módulo já está anexado.');
    }

    private function invalidResponse(): never { throw new ModuleAttachmentException('invalid_response', 'Resposta inválida do módulo.'); }
    private function matchesCompatibility(string $version,array $ranges):bool { foreach($ranges as $range){if(preg_match('/^(\d+)\.x$/',$range,$m)&&str_starts_with($version,$m[1].'.'))return true;if($range===$version)return true;}return false; }

    private function recordFailure(int $userId, string $ip, string $code): void
    {
        $this->limiter->recordFailure($userId, $ip);
        $this->safeAudit('project.attachment_failed', 'Falha ao anexar módulo.', 'failure', $userId, ['error_code' => $code]);
    }
    private function safeAudit(string $action,string $description,string $result,int $userId,array $metadata):void { try { Logger::activity($action,$description,$result,$userId,$metadata); } catch (Throwable) {} }
}
