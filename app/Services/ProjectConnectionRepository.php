<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class ProjectConnectionRepository
{
    public function __construct(private readonly PDO $db) {}
    public function connectionForProject(int $projectId): ?array { $s=$this->db->prepare('SELECT pc.*,u.name attached_by_name FROM project_connections pc LEFT JOIN users u ON u.id=pc.attached_by_user_id WHERE pc.project_id=? LIMIT 1');$s->execute([$projectId]);return $s->fetch()?:null; }
    public function connectionByInstance(string $instance): ?array { $s=$this->db->prepare('SELECT * FROM project_connections WHERE module_instance_id=? LIMIT 1');$s->execute([$instance]);return $s->fetch()?:null; }
    public function projectByCode(string $code): ?array { $s=$this->db->prepare('SELECT * FROM projects WHERE code=? AND deleted_at IS NULL LIMIT 1');$s->execute([$code]);return $s->fetch()?:null; }

    public function saveDiscovered(array $module, int $userId): array
    {
        $connection=$this->connectionByInstance($module['uuid']);
        $project=$connection?$this->projectById((int)$connection['project_id']):$this->projectByCode($module['project_code']);
        if($project&&!$connection){$current=$this->connectionForProject((int)$project['id']);if($current&&!hash_equals((string)$current['module_instance_id'],$module['uuid']))throw new ModuleAttachmentException('module_attached','Este projeto já está vinculado a outro módulo.');$connection=$current?:null;}
        $projectStatus=in_array(strtolower($module['status']),['active','production','online','operational'],true)?'active':'deploying';
        if($project){$projectId=(int)$project['id'];$this->db->prepare('UPDATE projects SET name=?,full_name=?,description=?,icon=?,version=?,url=?,admin_url=?,category=?,status=? WHERE id=?')->execute([$module['name'],$module['full_name'],$module['description'],$module['icon'],$module['version'],$module['url'],$module['admin_url'],$module['category'],$projectStatus,$projectId]);}
        else{$stmt=$this->db->prepare('INSERT INTO projects(name,full_name,code,description,icon,version,url,admin_url,category,status,display_order)VALUES(?,?,?,?,?,?,?,?,?,?,999)');$stmt->execute([$module['name'],$module['full_name'],$module['project_code'],$module['description'],$module['icon'],$module['version'],$module['url'],$module['admin_url'],$module['category'],$projectStatus]);$projectId=(int)$this->db->lastInsertId();}
        $now=date('Y-m-d H:i:s');$values=[json_encode($module['capabilities'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$module['code'],$module['slug'],$module['type'],$module['status'],$module['api_version'],json_encode($module['health'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),json_encode($module['compatibility'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$module['minimum_version'],$module['connection_status'],$now,$now];
        if($connection){$this->db->prepare('UPDATE project_connections SET project_id=?,endpoint_url=?,capabilities_json=?,module_code=?,module_slug=?,module_type=?,module_status=?,api_version=?,health_json=?,compatibility_json=?,minimum_version=?,integration_status=?,last_verified_at=?,last_communication_at=?,last_error_code=NULL WHERE id=?')->execute([$projectId,$module['url'],...$values,$connection['id']]);}
        else{$this->db->prepare("INSERT INTO project_connections(project_id,module_instance_id,protocol_version,endpoint_url,connection_status,capabilities_json,module_code,module_slug,module_type,module_status,api_version,health_json,compatibility_json,minimum_version,integration_status,attached_by_user_id,attached_at,last_verified_at,last_communication_at)VALUES(?,?,2,?,'attached',?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$projectId,$module['uuid'],$module['url'],$values[0],$values[1],$values[2],$values[3],$values[4],$values[5],$values[6],$values[7],$values[8],$values[9],$userId,$now,$now,$now]);}
        return ['project_id'=>$projectId,'created'=>$project===null,'updated'=>$project!==null];
    }

    public function markIntegrated(string $moduleUuid,string $encryptedCredential,string $requestId):array
    {
        $connection=$this->connectionByInstance($moduleUuid);if(!$connection)throw new ModuleAttachmentException('module_not_attached','Módulo não anexado.');$now=date('Y-m-d H:i:s');
        $this->db->prepare("UPDATE project_connections SET integration_status='integrado',connection_status='verified',machine_credential_encrypted=?,pairing_request_id=?,integrated_at=?,last_verified_at=?,last_communication_at=?,last_error_code=NULL WHERE id=?")->execute([$encryptedCredential,$requestId,$now,$now,$now,$connection['id']]);
        return $this->connectionByInstance($moduleUuid)??[];
    }

    public function updateCommunication(string $moduleUuid,string $status):void{$this->db->prepare('UPDATE project_connections SET integration_status=?,last_communication_at=?,last_verified_at=? WHERE module_instance_id=?')->execute([$status,date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$moduleUuid]);}
    private function projectById(int $id):?array{$s=$this->db->prepare('SELECT * FROM projects WHERE id=? AND deleted_at IS NULL LIMIT 1');$s->execute([$id]);return$s->fetch()?:null;}

    public function saveOfficial(array $module, string $baseUrl, int $userId): array
    {
        $existingProject = $this->projectByCode($module['project_code']);
        if ($this->connectionByInstance($module['uuid'])) throw new ModuleAttachmentException('duplicate_uuid', 'Este módulo já está anexado.');
        if ($existingProject && $this->connectionForProject((int) $existingProject['id'])) throw new ModuleAttachmentException('module_attached', 'Este módulo já está anexado.');
        $projectStatus = match (strtolower($module['status'])) { 'active','production','online','operational' => 'active', 'inactive','offline','disabled' => 'inactive', default => 'deploying' };
        if ($existingProject) {
            $projectId = (int) $existingProject['id'];
            $this->db->prepare('UPDATE projects SET name=?,full_name=?,description=?,icon=?,version=?,url=?,admin_url=?,category=?,status=? WHERE id=?')->execute([$module['name'],$module['full_name'],$module['description'],$module['icon'],$module['version'],$module['url'],$module['admin_url'],$module['category'],$projectStatus,$projectId]);
        } else {
            $s=$this->db->prepare('INSERT INTO projects(name,full_name,code,description,icon,version,url,admin_url,category,status,display_order) VALUES(?,?,?,?,?,?,?,?,?,?,999)');
            $s->execute([$module['name'],$module['full_name'],$module['project_code'],$module['description'],$module['icon'],$module['version'],$module['url'],$module['admin_url'],$module['category'],$projectStatus]);
            $projectId=(int)$this->db->lastInsertId();
        }
        $now=date('Y-m-d H:i:s');
        $lastUpdate=$module['last_updated_at']?date('Y-m-d H:i:s',strtotime($module['last_updated_at'])):null;
        $this->db->prepare("INSERT INTO project_connections(project_id,module_instance_id,protocol_version,endpoint_url,connection_status,capabilities_json,module_code,module_slug,module_type,module_status,api_version,health_json,compatibility_json,minimum_version,attached_by_user_id,attached_at,last_verified_at,last_communication_at,last_module_update_at) VALUES(?,?,1,?,'attached',?,?,?,?,?,?,?,?,?,?, ?,?,?,?)")->execute([$projectId,$module['uuid'],$baseUrl,json_encode($module['capabilities'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$module['code'],$module['slug'],$module['type'],$module['status'],$module['api_version'],json_encode($module['health'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),json_encode($module['compatibility'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$module['minimum_version'],$userId,$now,$now,$now,$lastUpdate]);
        return ['project_id'=>$projectId,'created'=>$existingProject===null];
    }

    /** Compatibilidade com o protocolo legado já homologado. */
    public function save(array $manifest,string $endpoint,int $userId):array
    {
        $module=$manifest['module'];$instance=$manifest['connection']['module_instance_id'];$existingProject=$this->projectByCode($module['product']);$existingInstance=$this->connectionByInstance($instance);
        if($existingProject){$current=$this->connectionForProject((int)$existingProject['id']);if($current&&$current['module_instance_id']!==$instance)throw new ModuleAttachmentException('product_conflict','Existe outro módulo utilizando este identificador.');if($existingInstance&&(int)$existingInstance['project_id']!==(int)$existingProject['id'])throw new ModuleAttachmentException('instance_conflict','Este módulo já está anexado.');$projectId=(int)$existingProject['id'];$this->db->prepare('UPDATE projects SET name=?,full_name=?,description=?,icon=?,version=?,url=?,admin_url=?,category=?,status=? WHERE id=?')->execute([$module['name'],$module['full_name'],$module['description'],$module['icon'],$module['version'],$module['public_url'],$module['admin_url'],$module['category'],$module['status'],$projectId]);}else{if($existingInstance)throw new ModuleAttachmentException('instance_conflict','Este módulo já está anexado.');$s=$this->db->prepare('INSERT INTO projects(name,full_name,code,description,icon,version,url,admin_url,category,status,display_order) VALUES(?,?,?,?,?,?,?,?,?,?,999)');$s->execute([$module['name'],$module['full_name'],$module['product'],$module['description'],$module['icon'],$module['version'],$module['public_url'],$module['admin_url'],$module['category'],$module['status']]);$projectId=(int)$this->db->lastInsertId();}
        $capabilities=json_encode($manifest['capabilities'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$attached=date('Y-m-d H:i:s',strtotime($manifest['connection']['attached_at']));$now=date('Y-m-d H:i:s');
        if($existingInstance){$this->db->prepare("UPDATE project_connections SET endpoint_url=?,connection_status='attached',capabilities_json=?,public_key_fingerprint=?,last_verified_at=?,last_error_code=NULL,updated_at=? WHERE id=?")->execute([$endpoint,$capabilities,$manifest['public_key_fingerprint'],$now,$now,$existingInstance['id']]);$action='project.connection_updated';}else{$this->db->prepare("INSERT INTO project_connections(project_id,module_instance_id,protocol_version,endpoint_url,connection_status,capabilities_json,public_key_fingerprint,attached_by_user_id,attached_at,last_verified_at) VALUES(?,?,1,?,'attached',?,?,?,?,?)")->execute([$projectId,$instance,$endpoint,$capabilities,$manifest['public_key_fingerprint'],$userId,$attached,$now]);$action='project.attached';}
        return ['project_id'=>$projectId,'action'=>$action,'created'=>$existingProject===null];
    }
}
