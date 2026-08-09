<?php
declare(strict_types=1);
namespace App\Services;

final class ModuleEndpointPolicy
{
    public function __construct(
        private readonly string $allowedDomain='gda.app.br',
        private readonly mixed $resolver=null,
        private readonly ?string $platformUrl=null,
    ){}

    public function assertAllowed(string$url):string
    {
        if(strlen($url)>1000||!filter_var($url,FILTER_VALIDATE_URL))$this->deny();$parts=parse_url($url);$host=strtolower((string)($parts['host']??''));$allowed=strtolower(trim($this->allowedDomain,'.'));
        if(($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||(isset($parts['port'])&&(int)$parts['port']!==443))$this->deny();
        if($host===''||filter_var($host,FILTER_VALIDATE_IP)||!($host===$allowed||str_ends_with($host,'.'.$allowed)))$this->deny();return$url;
    }

    public function publicAddresses(string$url):array
    {
        $host=(string)parse_url($this->assertAllowed($url),PHP_URL_HOST);$addresses=is_callable($this->resolver)?($this->resolver)($host):gethostbynamel($host);if(!is_array($addresses)||!$addresses)throw new ModuleAttachmentException('module_unavailable','Não foi possível localizar o módulo.');
        foreach($addresses as$address)if(filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false)$this->deny();return array_values(array_unique($addresses));
    }

    public function route(string$url):array
    {
        $publicAddresses=$this->publicAddresses($url);$platformUrl=$this->knownPlatformUrl();$internal=false;
        if($platformUrl!==null){try{$internal=array_intersect($publicAddresses,$this->publicAddresses($platformUrl))!==[];}catch(ModuleAttachmentException){$internal=false;}}
        return['public_addresses'=>$publicAddresses,'internal_proxy'=>$internal];
    }

    public function assertPublicResolution(string$url):void{$this->publicAddresses($url);}

    private function knownPlatformUrl():?string
    {
        $configured=trim((string)($this->platformUrl??getenv('APP_URL')?:''));if($configured!=='')return$configured;
        $host=trim((string)($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??''));if($host===''||str_contains($host,'/'))return null;
        return'https://'.$host;
    }

    private function deny():never{throw new ModuleAttachmentException('endpoint_denied','O endereço informado não é permitido.');}
}
