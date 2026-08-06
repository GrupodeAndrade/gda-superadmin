<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\DashboardService;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->view('dashboard/index',(new DashboardService($this->db,base_path()))->overview());
    }
}
