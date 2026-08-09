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
            $removed=$this->deleteClientData((int)$id);
            Logger::activity('client_deleted',"Cliente {$client['name']} excluído.",'success',null,['client_id'=>(int)$id,'client_name'=>$client['name'],'identifier'=>$client['identifier'],'removed'=>$removed]);
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
    private function deleteClientData(int $id): array
    {
        $removed=['module_access_issuances'=>0,'client_projects'=>0,'users'=>0];
        if($this->tableExists('module_access_issuances')) {
            $statement=$this->db->prepare("DELETE FROM module_access_issuances WHERE issued_by_user_id IN (SELECT id FROM users WHERE client_id=? AND role<>'super_admin')");
            $statement->execute([$id]);
            $removed['module_access_issuances']=$statement->rowCount();
        }
        $statement=$this->db->prepare('DELETE FROM client_projects WHERE client_id=?');
        $statement->execute([$id]);
        $removed['client_projects']=$statement->rowCount();
        $statement=$this->db->prepare("UPDATE users SET client_id=NULL WHERE client_id=? AND role='super_admin'");
        $statement->execute([$id]);
        $statement=$this->db->prepare("DELETE FROM users WHERE client_id=? AND role<>'super_admin'");
        $statement->execute([$id]);
        $removed['users']=$statement->rowCount();
        $statement=$this->db->prepare('DELETE FROM clients WHERE id=?');
        $statement->execute([$id]);
        if($statement->rowCount()!==1)throw new \RuntimeException('O cliente não foi removido exatamente uma vez.');
        return $removed;
    }
    private function tableExists(string $table): bool
    {
        if($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite') {
            $statement=$this->db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?");
        } else {
            $statement=$this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        }
        $statement->execute([$table]);
        return (int)$statement->fetchColumn()===1;
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
