<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/autoload.php';

$root=dirname(__DIR__).'/app';
$classes=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($iterator as$file){
    if(!$file->isFile()||$file->getExtension()!=='php')continue;
    $tokens=token_get_all((string)file_get_contents($file->getPathname()));
    $namespace='';
    $count=count($tokens);
    for($i=0;$i<$count;$i++){
        if(!is_array($tokens[$i]))continue;
        if($tokens[$i][0]===T_NAMESPACE){
            $namespace='';
            for($i++;$i<$count;$i++){
                if(is_string($tokens[$i])&&in_array($tokens[$i],[';','{'],true))break;
                if(is_array($tokens[$i])&&in_array($tokens[$i][0],[T_STRING,T_NAME_QUALIFIED,T_NS_SEPARATOR],true))$namespace.=$tokens[$i][1];
            }
        }
        if(in_array($tokens[$i][0],[T_CLASS,T_INTERFACE,T_TRAIT],true)){
            $previous=$i-1;while($previous>=0&&is_array($tokens[$previous])&&$tokens[$previous][0]===T_WHITESPACE)$previous--;
            if($previous>=0&&is_array($tokens[$previous])&&$tokens[$previous][0]===T_NEW)continue;
            for($i++;$i<$count;$i++)if(is_array($tokens[$i])&&$tokens[$i][0]===T_STRING){
                $class=($namespace!==''?$namespace.'\\':'').$tokens[$i][1];
                if(isset($classes[$class]))throw new RuntimeException("Classe duplicada: {$class}");
                $classes[$class]=$file->getPathname();
                break;
            }
        }
    }
}

foreach($classes as$class=>$file){
    if(!class_exists($class)&&!interface_exists($class)&&!trait_exists($class))throw new RuntimeException("Autoload falhou para {$class} em {$file}");
}

echo 'Autoload tests: '.count($classes).' unique classes loaded'.PHP_EOL;
