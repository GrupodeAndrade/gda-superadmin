<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Auth;
use App\Services\Logger;

final class AuthController extends BaseController
{
    public function show(): void
    {
        if (Auth::check()) redirect('dashboard');
        $this->view('auth/login', [], 'guest');
    }
    public function login(): void
    {
        $email = mb_strtolower($this->input('email'));
        $password = (string) ($_POST['password'] ?? '');
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? 'desconhecido', 0, 45);
        $recent = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND successful=0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $recent->execute([$ip]);
        if ((int) $recent->fetchColumn() >= 10) {
            flash('error', 'Muitas tentativas. Aguarde 15 minutos.');
            redirect('login');
        }
        $ok = filter_var($email, FILTER_VALIDATE_EMAIL) && Auth::attempt($email, $password) && Auth::isSuperAdmin();
        $this->db->prepare('INSERT INTO login_attempts (email,ip_address,successful) VALUES (?,?,?)')->execute([$email,$ip,$ok ? 1 : 0]);
        if (!$ok) {
            Logger::activity('login_invalid', 'Tentativa de login inválida.', 'failure', null);
            $_SESSION['_old']['email'] = $email;
            flash('error', 'E-mail ou senha inválidos.');
            redirect('login');
        }
        $this->db->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$_SESSION['user_id']]);
        Logger::activity('login', 'Login realizado com sucesso.');
        redirect('dashboard');
    }
    public function logout(): void
    {
        Logger::activity('logout', 'Sessão encerrada.');
        Auth::logout();
        session_start();
        flash('success', 'Você saiu com segurança.');
        redirect('login');
    }
}
