<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Http;

final class GlobalRouter{
 private array $routes=[];
 public function add(string $method,string $pattern,callable $handler,bool $public=false):void{$regex=preg_replace('#\{id\}#','(?P<id>\d+)',$pattern);$this->routes[]=[$method,'#^'.$regex.'/?$#',$handler,$public];}
 public function dispatch(string $method,string $path,callable $authorize):void{foreach($this->routes as[$verb,$regex,$handler,$public]){if($verb!==$method||!preg_match($regex,$path,$matches))continue;if(!$public)$authorize();$handler(array_filter($matches,'is_string',ARRAY_FILTER_USE_KEY));return;}http_response_code(404);echo'Página global não encontrada.';}
}
