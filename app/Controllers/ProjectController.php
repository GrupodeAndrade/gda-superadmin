<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Logger;

final class ProjectController extends BaseController
{
    public function index(): void
    {
        $projects = $this->db->query("SELECT p.*,COUNT(CASE WHEN cp.status='active' THEN 1 END) client_count FROM projects p LEFT JOIN client_projects cp ON cp.project_id=p.id WHERE p.deleted_at IS NULL GROUP BY p.id ORDER BY p.display_order,p.name")->fetchAll();
        $this->view('projetos/index', compact('projects'));
    }

    public function show(string $id): void
    {
        $project = $this->find('projects', (int) $id);
        $statement = $this->db->prepare("SELECT c.* FROM clients c JOIN client_projects cp ON cp.client_id=c.id WHERE cp.project_id=? AND cp.status='active' ORDER BY c.name");
        $statement->execute([$id]);
        $clients = $statement->fetchAll();
        $activity = $this->db->prepare("SELECT l.*,u.name user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id WHERE JSON_UNQUOTE(JSON_EXTRACT(l.metadata,'$.project_id'))=? ORDER BY l.created_at DESC LIMIT 5");
        $activity->execute([(string) $id]);
        $activities = $activity->fetchAll();
        $this->view('projetos/show', compact('project', 'clients', 'activities'));
    }

    public function administer(string $id): void
    {
        $project = $this->find('projects', (int) $id);
        $url = trim((string) ($project['admin_url'] ?? ''));
        $parts = $url !== '' ? parse_url($url) : false;
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http','https'], true) || empty($parts['host'])) {
            flash('error', 'URL administrativa não configurada.');
            redirect('projetos/'.$id);
        }
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('Location: '.$url, true, 302);
        exit;
    }

    public function edit(string $id): void
    {
        $project = $this->find('projects', (int) $id);
        $this->view('projetos/form', compact('project'));
    }

    public function update(string $id): void
    {
        $project=$this->find('projects',(int)$id);$name=$this->input('name');$fullName=$this->input('full_name');$description=$this->input('description');$version=$this->input('version');$url=$this->input('url');$adminUrl=$this->input('admin_url');$category=$this->input('category');$icon=$this->input('icon','grid');$status=$this->input('status');$order=(int)$this->input('display_order','0');
        if($name===''||!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',$version)||($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))||($adminUrl!==''&&!filter_var($adminUrl,FILTER_VALIDATE_URL))||!in_array($status,['active','inactive','deploying'],true)){flash('error','Revise os campos informados.');redirect("projetos/$id/editar");}
        $this->db->prepare('UPDATE projects SET name=?,full_name=?,description=?,icon=?,version=?,url=?,admin_url=?,category=?,status=?,display_order=? WHERE id=?')->execute([$name,$fullName,$description,$icon,$version,$url,$adminUrl,$category,$status,$order,$id]);
        Logger::activity('project_updated',"Projeto {$project['name']} alterado.",'success',null,['project_id'=>(int)$id]);flash('success','Projeto salvo com sucesso.');redirect("projetos/$id");
    }

    public function toggle(string $id): void { $p=$this->find('projects',(int)$id);$new=$p['status']==='active'?'inactive':'active';$this->db->prepare('UPDATE projects SET status=? WHERE id=?')->execute([$new,$id]);Logger::activity('project_status',"Projeto {$p['name']} " . ($new==='active'?'ativado':'inativado') . '.','success',null,['project_id'=>(int)$id]);flash('success','Status do projeto atualizado.');redirect('projetos'); }
    public function clients(string $id): void { $this->show($id); }
}
