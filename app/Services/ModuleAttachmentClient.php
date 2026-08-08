<?php
declare(strict_types=1);

namespace App\Services;

final class ModuleAttachmentClient
{
    public function __construct(private readonly ModuleEndpointPolicy $policy, private readonly int $timeout = 5, private readonly int $maxBytes = 262144, private readonly mixed $transport = null) {}

    public function request(string $endpoint, array $body): array
    {
        $endpoint = $this->policy->assertAllowed($endpoint);
        if (is_callable($this->transport)) return ($this->transport)($endpoint, $body, $this->timeout, $this->maxBytes);
        $this->policy->assertPublicResolution($endpoint);
        if (!function_exists('curl_init')) throw new ModuleAttachmentException('transport_unavailable', 'Falha ao conectar ao módulo.');
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $received = '';
        $type = '';
        $tooLarge = false;
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>$this->timeout,CURLOPT_TIMEOUT=>$this->timeout,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$type){if(stripos($line,'Content-Type:')===0)$type=trim(substr($line,13));return strlen($line);},CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$received,&$tooLarge){if(strlen($received)+strlen($chunk)>$this->maxBytes){$tooLarge=true;return 0;}$received.=$chunk;return strlen($chunk);}]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($curl);
        curl_close($curl);
        if ($tooLarge) throw new ModuleAttachmentException('response_too_large', 'Resposta inválida do módulo.');
        if ($errno === CURLE_OPERATION_TIMEDOUT) throw new ModuleAttachmentException('timeout', 'O módulo não respondeu dentro do prazo.');
        if (in_array($errno, [CURLE_SSL_CERTPROBLEM,CURLE_PEER_FAILED_VERIFICATION,CURLE_SSL_CACERT_BADFILE], true)) throw new ModuleAttachmentException('invalid_certificate', 'Certificado inválido.');
        if (!$ok || $errno) throw new ModuleAttachmentException('connection_failed', 'Falha ao conectar ao módulo.');
        $decoded = null;
        if ($received !== '') {
            try { $decoded = json_decode($received, true, 32, JSON_THROW_ON_ERROR); } catch (\JsonException) {}
        }
        if ($status < 200 || $status >= 300) $this->remoteError($status, is_array($decoded) ? $decoded : []);
        if (!str_starts_with(strtolower($type), 'application/json')) throw new ModuleAttachmentException('invalid_content_type', 'Resposta inválida do módulo.');
        if (!is_array($decoded)) throw new ModuleAttachmentException('invalid_json', 'JSON inválido retornado pelo módulo.');
        return $decoded;
    }

    private function remoteError(int $status, array $response): never
    {
        $error = $response['error'] ?? null;
        $code = strtolower((string) (is_array($error) ? ($error['code'] ?? '') : ($response['error_code'] ?? $response['code'] ?? (is_string($error) ? $error : ''))));
        $known = [
            'invalid_key' => ['invalid_key','attachment_key_invalid','key_invalid','invalid_or_expired_key'],
            'expired_key' => ['expired_key','attachment_key_expired','key_expired'],
            'used_key' => ['used_key','attachment_key_used','key_used','already_used'],
        ];
        foreach ($known as $safe => $aliases) if (in_array($code, $aliases, true)) {
            $message = ['invalid_key'=>'Chave inválida.','expired_key'=>'Chave expirada.','used_key'=>'Chave já utilizada.'][$safe];
            throw new ModuleAttachmentException($safe, $message);
        }
        if ($status === 404) throw new ModuleAttachmentException('endpoint_not_found', 'Endpoint de integração não encontrado.');
        if (in_array($status, [401,403,410,422], true)) throw new ModuleAttachmentException('invalid_key', 'Chave inválida.');
        throw new ModuleAttachmentException('module_unavailable', 'Módulo indisponível.');
    }
}
