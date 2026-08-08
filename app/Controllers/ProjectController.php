<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Auth;
use App\Services\Logger;
use App\Services\ModuleAttachmentClient;
use App\Services\ModuleAttachmentException;
use App\Services\ModuleAttachmentRateLimiter;
use App\Services\ModuleEndpointPolicy;
use App\Services\ModuleAttachmentService;
use App\Services\ProjectConnectionRepository;

final class ProjectController extends BaseController
{
    public function index(): void { $projects=$this->db->query("SELECT p.*,COUNT(CASE WHEN cp.status='active' THEN 1 END) client_count FROM projects p LEFT JOIN client_projects cp ON cp.project_id=p.id WHERE p.deleted_at IS NULL GROUP BY p.id ORDER BY p.display_order,p.name")->fetchAll();$this->view('projetos/index',compact('projects')); }
    public function show(string $id): void { $project=$this->find('projects',(int)$id);$s=$this->db->prepare("SELECT c.* FROM clients c JOIN client_projects cp ON cp.client_id=c.id WHERE cp.project_id=? AND cp.status='active' ORDER BY c.name");$s->execute([$id]);$clients=$s->fetchAll();$a=$this->db->prepare("SELECT l.*,u.name user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id WHERE JSON_UNQUOTE(JSON_EXTRACT(l.metadata,'$.project_id'))=? ORDER BY l.created_at DESC LIMIT 5");$a->execute([(string)$id]);$activities=$a->fetchAll();$connection=(new ProjectConnectionRepository($this->db))->connectionForProject((int)$id);if($connection)$connection['capabilities']=json_decode($connection['capabilities_json'],true)?:[];$this->view('projetos/show',compact('project','clients','activities','connection')); }
    public function attachForm(): void
    {
        $user=Auth::user();$pending=$_SESSION['_module_attachment_pending']??null;
        if(!is_array($pending)||($pending['user_id']??0)!==(int)$user['id']||time()-(int)($pending['validated_at']??0)>600){unset($_SESSION['_module_attachment_pending']);$pending=null;}
        $preview=$pending['module']??null;$this->view('projetos/attach',compact('preview'));
    }
    public function validateAttachment(): void
    {
        $user=Auth::user();
        try{$pending=$this->attachmentService()->validate($this->input('module_url'),$this->input('attachment_key'),(int)$user['id'],$_SERVER['REMOTE_ADDR']??'desconhecido',$this->platformVersion());$_SESSION['_module_attachment_pending']=$pending;flash('success','Módulo encontrado.');redirect('projetos/anexar');}
        catch(ModuleAttachmentException $e){unset($_SESSION['_module_attachment_pending']);flash('error',$e->getMessage());redirect('projetos/anexar');}
    }
    public function confirmAttachment(): void
    {
        $user=Auth::user();$pending=$_SESSION['_module_attachment_pending']??null;
        try{if(!is_array($pending))throw new ModuleAttachmentException('validation_required','Valide o módulo antes de confirmar.');$result=$this->attachmentService()->confirm($pending,(int)$user['id'],$_SERVER['REMOTE_ADDR']??'desconhecido',$this->platformVersion());unset($_SESSION['_module_attachment_pending']);$_SESSION['_attached_project']=$result['project_id'];flash('success','Módulo anexado com sucesso.');redirect('projetos/anexar/sucesso');}
        catch(ModuleAttachmentException $e){unset($_SESSION['_module_attachment_pending']);flash('error',$e->getMessage());redirect('projetos/anexar');}
    }
    public function cancelAttachment(): void { unset($_SESSION['_module_attachment_pending']);redirect('projetos'); }
    public function attachSuccess(): void { $id=(int)($_SESSION['_attached_project']??0);unset($_SESSION['_attached_project']);if(!$id)redirect('projetos');$project=$this->find('projects',$id);$connection=(new ProjectConnectionRepository($this->db))->connectionForProject($id);if(!$connection)redirect('projetos');$connection['capabilities']=json_decode($connection['capabilities_json'],true)?:[];$this->view('projetos/attach-success',compact('project','connection')); }
    public function edit(string $id): void { $project=$this->find('projects',(int)$id);$this->view('projetos/form',compact('project')); }
    public function update(string $id): void
    {
        $project=$this->find('projects',(int)$id);$name=$this->input('name');$fullName=$this->input('full_name');$description=$this->input('description');$version=$this->input('version');$url=$this->input('url');$adminUrl=$this->input('admin_url');$category=$this->input('category');$icon=$this->input('icon','grid');$status=$this->input('status');$order=(int)$this->input('display_order','0');
        if($name===''||!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',$version)||($url!==''&&!filter_var($url,FILTER_VALIDATE_URL))||($adminUrl!==''&&!filter_var($adminUrl,FILTER_VALIDATE_URL))||!in_array($status,['active','inactive','deploying'],true)){flash('error','Revise os campos informados.');redirect("projetos/$id/editar");}
        $this->db->prepare('UPDATE projects SET name=?,full_name=?,description=?,icon=?,version=?,url=?,admin_url=?,category=?,status=?,display_order=? WHERE id=?')->execute([$name,$fullName,$description,$icon,$version,$url,$adminUrl,$category,$status,$order,$id]);
        Logger::activity('project_updated',"Projeto {$project['name']} alterado.",'success',null,['project_id'=>(int)$id]);flash('success','Projeto salvo com sucesso.');redirect("projetos/$id");
    }
    public function toggle(string $id): void { $p=$this->find('projects',(int)$id);$new=$p['status']==='active'?'inactive':'active';$this->db->prepare('UPDATE projects SET status=? WHERE id=?')->execute([$new,$id]);Logger::activity('project_status',"Projeto {$p['name']} " . ($new==='active'?'ativado':'inativado') . '.','success',null,['project_id'=>(int)$id]);flash('success','Status do projeto atualizado.');redirect('projetos'); }
    public function clients(string $id): void { $this->show($id); }
    private function attachmentService(): ModuleAttachmentService { $config=require base_path('config/app.php');$settings=$config['module_attachment'];$policy=new ModuleEndpointPolicy($settings['allowed_domain']);return new ModuleAttachmentService($this->db,new ModuleAttachmentClient($policy,$settings['timeout'],$settings['max_response_bytes']),$policy,new ProjectConnectionRepository($this->db),new ModuleAttachmentRateLimiter()); }
    private function platformVersion(): string { return (string)(json_decode((string)file_get_contents(base_path('version.json')),true)['version']??'0.0.0'); }
}
