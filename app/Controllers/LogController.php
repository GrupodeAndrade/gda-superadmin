<?php
declare(strict_types=1);
namespace App\Controllers;

final class LogController extends BaseController
{
    public function index(): void
    {
        $where=['1=1'];$params=[];
        foreach(['action'=>'l.action','result'=>'l.result','user_id'=>'l.user_id'] as $key=>$col) if(($_GET[$key]??'')!==''){$where[]="$col=?";$params[]=$_GET[$key];}
        if(!empty($_GET['from'])){$where[]='DATE(l.created_at)>=?';$params[]=$_GET['from'];} if(!empty($_GET['to'])){$where[]='DATE(l.created_at)<=?';$params[]=$_GET['to'];}
        $stmt=$this->db->prepare('SELECT l.*,u.name user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id WHERE '.implode(' AND ',$where).' ORDER BY l.created_at DESC LIMIT 200');$stmt->execute($params);
        $logs=$stmt->fetchAll();$users=$this->db->query('SELECT id,name FROM users ORDER BY name')->fetchAll();$actions=$this->db->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(\PDO::FETCH_COLUMN);
        $this->view('logs/index',compact('logs','users','actions'));
    }
}

