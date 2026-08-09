<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Auth;
use App\Services\Logger;

final class SettingsController extends BaseController
{
    public function index(): void { $settings=$this->settings();$diagnostic=$this->diagnostic();$this->view('configuracoes/index',compact('settings','diagnostic')); }
    public function update(): void
    {
        $allowed=['platform_name','app_url','company_name','admin_email','timezone','max_update_mb','maintenance_mode','maintenance_message'];
        if(!filter_var($_POST['app_url']??'',FILTER_VALIDATE_URL)||!filter_var($_POST['admin_email']??'',FILTER_VALIDATE_EMAIL)){flash('error','Revise a URL e o e-mail.');redirect('configuracoes');}
        $stmt=$this->db->prepare('INSERT INTO settings (setting_key,setting_value,type) VALUES (?,?,"string") ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach($allowed as $key)$stmt->execute([$key,(string)($_POST[$key]??'0')]);
        Logger::activity('settings_updated','Configurações gerais alteradas.');flash('success','Configurações salvas com sucesso.');redirect('configuracoes');
    }
    public function profile(): void { $this->view('configuracoes/perfil',['user'=>Auth::user()]); }
    public function updateProfile(): void
    {
        $user=Auth::user();$current=(string)($_POST['current_password']??'');$s=$this->db->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$user['id']]);
        if(!password_verify($current,(string)$s->fetchColumn())){flash('error','A senha atual não confere.');redirect('perfil');}
        $name=$this->input('name');$email=mb_strtolower($this->input('email'));$password=(string)($_POST['new_password']??'');
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||($password!==''&&(strlen($password)<10||$password!==($_POST['new_password_confirmation']??'')))){flash('error','Revise os dados do perfil e a confirmação da nova senha.');redirect('perfil');}
        if($password!=='')$this->db->prepare('UPDATE users SET name=?,email=?,password_hash=? WHERE id=?')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$user['id']]);else $this->db->prepare('UPDATE users SET name=?,email=? WHERE id=?')->execute([$name,$email,$user['id']]);
        Logger::activity('profile_updated','Perfil do Super Administrador alterado.');flash('success','Perfil atualizado com sucesso.');redirect('perfil');
    }
    private function settings():array { return $this->db->query('SELECT setting_key,setting_value FROM settings')->fetchAll(\PDO::FETCH_KEY_PAIR); }
    private function diagnostic():array
    {
        $folders=[];foreach(['config','storage/backups','storage/cache','storage/logs','storage/temp','storage/updates'] as $f)$folders[$f]=is_writable(base_path($f));
        return ['php'=>PHP_VERSION,'php_ok'=>version_compare(PHP_VERSION,'8.3.0','>='),'pdo'=>extension_loaded('pdo'),'pdo_mysql'=>extension_loaded('pdo_mysql'),'zip'=>extension_loaded('zip'),'upload_max'=>ini_get('upload_max_filesize'),'post_max'=>ini_get('post_max_size'),'db'=>true,'version'=>json_decode((string)file_get_contents(base_path('version.json')),true)['version']??'?','folders'=>$folders];
    }
}
