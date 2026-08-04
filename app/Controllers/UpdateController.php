<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Auth;
use App\Services\Logger;
use App\Services\UpdateService;

final class UpdateController extends BaseController
{
    public function index():void
    {
        $version=json_decode((string)file_get_contents(base_path('version.json')),true);$history=$this->db->query('SELECT su.*,u.name user_name FROM system_updates su LEFT JOIN users u ON u.id=su.user_id ORDER BY su.created_at DESC LIMIT 20')->fetchAll();$backups=glob(base_path('storage/backups/*.zip'))?:[];$lastBackup=$backups?date('d/m/Y H:i',max(array_map('filemtime',$backups))):'Nenhum';
        $this->view('atualizacoes/index',compact('version','history','lastBackup'));
    }
    public function analyze():void
    {
        $settings=$this->db->query("SELECT setting_value FROM settings WHERE setting_key='max_update_mb'")->fetchColumn()?:50;$file=$_FILES['package']??null;
        if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>(int)$settings*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='zip'){flash('error','Selecione um ZIP válido dentro do limite configurado.');redirect('atualizacoes');}
        $stored=base_path('storage/updates/'.bin2hex(random_bytes(12)).'.zip');if(!move_uploaded_file($file['tmp_name'],$stored)){flash('error','Não foi possível armazenar o pacote.');redirect('atualizacoes');}
        try{$manifest=(new UpdateService())->analyze($stored);$hash=hash_file('sha256',$stored);$_SESSION['update_pending']=['path'=>$stored,'manifest'=>$manifest,'sha256'=>$hash,'name'=>basename($file['name']),'size'=>$file['size'],'backup'=>isset($_POST['backup'])];$stmt=$this->db->prepare("INSERT INTO system_updates(previous_version,new_version,filename,file_size,user_id,status,summary,manifest) VALUES(?,?,?,?,?,'analyzed',?,?)");$stmt->execute([$manifest['_current'],$manifest['version'],basename($file['name']),$file['size'],Auth::user()['id'],$manifest['description'],json_encode($manifest)]);Logger::activity('update_analyzed',"Pacote {$manifest['version']} analisado.",'success',null,['sha256'=>$hash]);$this->view('atualizacoes/confirm',['pending'=>$_SESSION['update_pending']]);}
        catch(\Throwable $e){@unlink($stored);Logger::activity('update_failed','Pacote de atualização rejeitado: '.$e->getMessage(),'failure');flash('error',$e->getMessage());redirect('atualizacoes');}
    }
    public function install():void
    {
        $pending=$_SESSION['update_pending']??null;$password=(string)($_POST['password']??'');$s=$this->db->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([Auth::user()['id']]);
        if(!$pending||!is_file($pending['path'])||!password_verify($password,(string)$s->fetchColumn())){flash('error','Pacote pendente ou senha de confirmação inválida.');redirect('atualizacoes');}
        try{
            $currentHash=hash_file('sha256',$pending['path']);
            if(!is_string($currentHash)||!hash_equals($pending['sha256']??'',$currentHash))throw new \RuntimeException('O pacote foi alterado após a análise.');
            $service=new UpdateService();$manifest=$service->analyze($pending['path']);
            if($manifest!=$pending['manifest'])throw new \RuntimeException('O manifesto não corresponde ao pacote analisado.');
            $this->db->prepare("UPDATE system_updates SET status='installing' WHERE new_version=? ORDER BY id DESC LIMIT 1")->execute([$manifest['version']]);$backup=$service->install($pending['path'],$manifest,$pending['backup']);$this->db->prepare("UPDATE system_updates SET status='completed',backup_path=? WHERE new_version=? ORDER BY id DESC LIMIT 1")->execute([$backup,$manifest['version']]);Logger::activity('update_installed',"Versão {$manifest['version']} instalada.");@unlink($pending['path']);unset($_SESSION['update_pending']);flash('success','Atualização concluída com sucesso.');
        } catch(\App\Services\UpdateFailureException $e){
            $status=$e->rollbackCompleted()?'restored':'failed';$this->db->prepare("UPDATE system_updates SET status=?,internal_error=? WHERE new_version=? ORDER BY id DESC LIMIT 1")->execute([$status,$e->getMessage(),$pending['manifest']['version']]);
            if($e->rollbackCompleted()){Logger::activity('update_restored','Atualização falhou; rollback concluído.','warning');flash('error','A atualização falhou, mas os arquivos anteriores foram restaurados.');}
            else{Logger::activity('rollback_failed','Atualização e rollback falharam; manutenção mantida.','failure');flash('error','A atualização e a restauração falharam. O sistema permanece em manutenção; consulte o log interno.');}
        } catch(\Throwable $e){$this->db->prepare("UPDATE system_updates SET status='failed',internal_error=? WHERE new_version=? ORDER BY id DESC LIMIT 1")->execute([$e->getMessage(),$pending['manifest']['version']]);Logger::activity('update_failed','Pacote recusado antes da instalação: '.$e->getMessage(),'failure');flash('error','O pacote não corresponde à análise anterior e não foi instalado.');}
        redirect('atualizacoes');
    }
}
