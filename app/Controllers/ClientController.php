<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Logger;

final class ClientController extends BaseController
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? '')); $status = $_GET['status'] ?? ''; $page = max(1, (int) ($_GET['page'] ?? 1)); $perPage = 15;
        $where = ['c.deleted_at IS NULL']; $params = [];
        if ($q !== '') { $where[] = '(c.name LIKE ? OR c.identifier LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)'; $like = "%$q%"; array_push($params,$like,$like,$like,$like); }
        if (in_array($status,['active','inactive'],true)) { $where[]='c.status=?'; $params[]=$status; }
        $sqlWhere=implode(' AND ',$where);
        $count=$this->db->prepare("SELECT COUNT(*) FROM clients c WHERE $sqlWhere"); $count->execute($params); $total=(int)$count->fetchColumn();
        $stmt=$this->db->prepare("SELECT c.*,COUNT(CASE WHEN cp.status='active' THEN 1 END) project_count FROM clients c LEFT JOIN client_projects cp ON cp.client_id=c.id WHERE $sqlWhere GROUP BY c.id ORDER BY c.created_at DESC LIMIT $perPage OFFSET " . (($page-1)*$perPage));
        $stmt->execute($params);
        $this->view('clientes/index',['clients'=>$stmt->fetchAll(),'q'=>$q,'status'=>$status,'page'=>$page,'pages'=>max(1,(int)ceil($total/$perPage))]);
    }
    public function create(): void { $projects=$this->db->query("SELECT * FROM projects WHERE status IN ('active','deploying') AND deleted_at IS NULL ORDER BY display_order,name")->fetchAll(); $this->view('clientes/form',compact('projects')); }
    public function store(): void
    {
        $data=$this->validate(true); if ($data['errors']) { $_SESSION['_old']=$_POST; foreach($data['errors'] as $e) flash('error',$e); redirect('clientes/novo'); }
        $this->db->beginTransaction();
        try {
            $identifier=$this->generateIdentifier();
            $stmt=$this->db->prepare('INSERT INTO clients (identifier,name,administrator_name,email,phone,status,notes) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$identifier,$data['name'],$data['administrator_name'],$data['email'],$data['phone'],$data['status'],$data['notes']]); $id=(int)$this->db->lastInsertId();
            $this->db->prepare('INSERT INTO users (client_id,name,email,password_hash,role,status) VALUES (?,?,?,?,\'client_admin\',?)')->execute([$id,$data['administrator_name'],$data['email'],password_hash($_POST['password'],PASSWORD_DEFAULT),$data['status']]);
            $this->syncProjects($id,$_POST['projects']??[]);
            $this->db->commit(); Logger::activity('client_created',"Cliente {$data['name']} cadastrado.",'success',null,['client_id'=>$id]); flash('success','Cliente salvo com sucesso.'); redirect("clientes/$id");
        } catch (\Throwable $e) { $this->db->rollBack(); flash('error','Não foi possível cadastrar. O identificador ou e-mail pode já estar em uso.'); redirect('clientes/novo'); }
    }
    public function show(string $id): void { $client=$this->find('clients',(int)$id); $projects=$this->clientProjects((int)$id); $this->view('clientes/show',compact('client','projects')); }
    public function edit(string $id): void { $client=$this->find('clients',(int)$id); $projects=$this->db->query("SELECT * FROM projects WHERE deleted_at IS NULL ORDER BY display_order,name")->fetchAll(); $selected=array_column($this->clientProjects((int)$id),'id'); $this->view('clientes/form',compact('client','projects','selected')); }
    public function update(string $id): void
    {
        $client=$this->find('clients',(int)$id); $data=$this->validate(false,(int)$id);
        if ($data['errors']) { foreach($data['errors'] as $e) flash('error',$e); redirect("clientes/$id/editar"); }
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE clients SET name=?,administrator_name=?,email=?,phone=?,status=?,notes=? WHERE id=?')->execute([$data['name'],$data['administrator_name'],$data['email'],$data['phone'],$data['status'],$data['notes'],$id]);
            $this->db->prepare('UPDATE users SET name=?,email=?,status=? WHERE client_id=? AND role=\'client_admin\'')->execute([$data['administrator_name'],$data['email'],$data['status'],$id]);
            $this->syncProjects((int)$id,$_POST['projects']??[]); $this->db->commit();
            Logger::activity('client_updated',"Cliente {$client['name']} alterado.",'success',null,['client_id'=>(int)$id]); flash('success','Cliente salvo com sucesso.'); redirect("clientes/$id");
        } catch (\Throwable $e) { $this->db->rollBack(); flash('error','Não foi possível salvar as alterações.'); redirect("clientes/$id/editar"); }
    }
    public function toggle(string $id): void
    {
        $client=$this->find('clients',(int)$id); $new=$client['status']==='active'?'inactive':'active';
        $this->db->prepare('UPDATE clients SET status=? WHERE id=?')->execute([$new,$id]); $this->db->prepare("UPDATE users SET status=? WHERE client_id=?")->execute([$new,$id]);
        Logger::activity('client_status',"Cliente {$client['name']} " . ($new==='active'?'ativado':'inativado') . '.'); flash('success','Status do cliente atualizado.'); redirect('clientes');
    }
    public function destroy(string $id): void
    {
        $client=$this->find('clients',(int)$id);
        $this->db->beginTransaction();
        try {
            if(!$this->deleteClientIfUnreferenced((int)$id)) {
                $this->db->rollBack();
                flash('error','Este cliente possui usuários, projetos ou outros vínculos e precisa ser desvinculado antes da exclusão.');
                redirect("clientes/$id");
            }
            Logger::activity('client_deleted',"Cliente {$client['name']} excluído.",'success',null,['client_id'=>(int)$id,'identifier'=>$client['identifier']]);
            $this->db->commit();
            flash('success','Cliente excluído com sucesso.');
            redirect('clientes');
        } catch (\Throwable $e) {
            if($this->db->inTransaction())$this->db->rollBack();
            flash('error','Não foi possível excluir o cliente porque existem vínculos ou registros relacionados.');
            redirect("clientes/$id");
        }
    }
    public function projects(string $id): void { $this->edit($id); }
    private function validate(bool $creating, ?int $id=null): array
    {
        $name=$this->input('name'); $administrator_name=$this->input('administrator_name'); $email=mb_strtolower($this->input('email')); $phone=$this->input('phone'); $status=$this->input('status'); $notes=$this->input('notes'); $errors=[];
        if($name===''||$administrator_name==='')$errors[]='Nome do cliente e pessoa administradora são obrigatórios.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Informe um e-mail válido.';
        if(!in_array($status,['active','inactive'],true))$errors[]='Status inválido.';
        if($creating&&(strlen($_POST['password']??'')<8||($_POST['password']??'')!==($_POST['password_confirmation']??'')))$errors[]='A senha provisória deve ter 8 caracteres e confirmação igual.';
        return compact('name','administrator_name','email','phone','status','notes','errors');
    }
    private function generateIdentifier(): string
    {
        $exists=$this->db->prepare('SELECT 1 FROM clients WHERE identifier=? LIMIT 1');
        for($attempt=0;$attempt<10;$attempt++) {
            $identifier='cliente-'.bin2hex(random_bytes(16));
            $exists->execute([$identifier]);
            if(!$exists->fetchColumn())return $identifier;
        }
        throw new \RuntimeException('Não foi possível gerar um identificador único.');
    }
    private function deleteClientIfUnreferenced(int $id): bool
    {
        foreach(['client_projects','users'] as $table) {
            $statement=$this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE client_id=?");
            $statement->execute([$id]);
            if((int)$statement->fetchColumn()>0)return false;
        }
        $statement=$this->db->prepare('DELETE FROM clients WHERE id=? AND NOT EXISTS (SELECT 1 FROM client_projects WHERE client_id=?) AND NOT EXISTS (SELECT 1 FROM users WHERE client_id=?)');
        $statement->execute([$id,$id,$id]);
        return $statement->rowCount()===1;
    }
    private function syncProjects(int $clientId,array $ids): void
    {
        $ids=array_values(array_unique(array_map('intval',$ids))); $active=$this->db->query("SELECT id FROM projects WHERE status IN ('active','deploying') AND deleted_at IS NULL")->fetchAll(\PDO::FETCH_COLUMN); $ids=array_values(array_intersect($ids,array_map('intval',$active)));
        $this->db->prepare("UPDATE client_projects SET status='inactive',deactivated_at=NOW() WHERE client_id=? AND status='active'")->execute([$clientId]);
        $stmt=$this->db->prepare("INSERT INTO client_projects (client_id,project_id,status,activated_at,settings) VALUES (?,?,'active',NOW(),JSON_OBJECT()) ON DUPLICATE KEY UPDATE status='active',activated_at=NOW(),deactivated_at=NULL");
        foreach($ids as $projectId)$stmt->execute([$clientId,$projectId]);
    }
    private function clientProjects(int $id): array { $s=$this->db->prepare("SELECT p.*,cp.activated_at FROM projects p JOIN client_projects cp ON cp.project_id=p.id WHERE cp.client_id=? AND cp.status='active' ORDER BY p.display_order,p.name");$s->execute([$id]);return $s->fetchAll(); }
}
