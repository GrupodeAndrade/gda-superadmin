<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Views;

final class GlobalView{
 public function __construct(private string $root){}
 public function render(string $view,array $data=[]):void{extract($data,EXTR_SKIP);$viewFile=$this->root.'/'.$view.'.php';if(!is_file($viewFile))throw new \RuntimeException('View global não encontrada.');require $this->root.'/header.php';require $viewFile;require $this->root.'/footer.php';}
}
