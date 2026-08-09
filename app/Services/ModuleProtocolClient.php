<?php
declare(strict_types=1);

namespace App\Services;

final class ModuleProtocolClient
{
    private const INTERNAL_PROXY = 'coolify-proxy';

    public function __construct(
        private readonly ModuleEndpointPolicy $policy,
        private readonly int $timeout = 5,
        private readonly int $maxBytes = 262144,
        private readonly mixed $transport = null,
    ) {}

    public function get(string $url, array $headers = []): array
    {
        return $this->send('GET', $url, null, $headers);
    }

    public function post(string $url, array $body, array $headers = []): array
    {
        return $this->send('POST', $url, $body, $headers);
    }

    private function send(string $method, string $url, ?array $body, array $headers): array
    {
        $url = $this->policy->assertAllowed($url);
        if (is_callable($this->transport)) {
            $response = ($this->transport)($method, $url, $body, $headers, $this->timeout, $this->maxBytes);
            return $this->decodeResponse($response);
        }
        $route = $this->policy->route($url);
        if (!function_exists('curl_init')) {
            throw new ModuleAttachmentException('transport_unavailable', 'Falha ao conectar ao módulo.');
        }
        $received = '';
        $responseType = '';
        $tooLarge = false;
        $requestHeaders = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) $requestHeaders[] = $name . ': ' . (string) $value;
        }
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseType): int {
                if (stripos($line, 'Content-Type:') === 0) $responseType = trim(substr($line, 13));
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$received, &$tooLarge): int {
                if (strlen($received) + strlen($chunk) > $this->maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $received .= $chunk;
                return strlen($chunk);
            },
        ];
        $options += $this->connectionRouteOptions($url, $route);
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $options[CURLOPT_POSTFIELDS] = $payload;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($curl);
        curl_close($curl);
        if ($tooLarge) throw new ModuleAttachmentException('response_too_large', 'Resposta inválida do módulo.');
        if ($errno === CURLE_OPERATION_TIMEDOUT) throw new ModuleAttachmentException('timeout', 'O módulo não respondeu dentro do prazo.');
        if (!$ok || $errno) throw new ModuleAttachmentException('connection_failed', 'Falha ao conectar ao módulo.');
        return $this->decodeResponse(['status' => $status, 'body' => $received, 'content_type' => $responseType]);
    }

    private function connectionRouteOptions(string $url, array $route): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (($route['internal_proxy'] ?? false) === true) {
            return [CURLOPT_CONNECT_TO => [$host.':443:'.self::INTERNAL_PROXY.':443']];
        }
        return [CURLOPT_RESOLVE => [$host.':443:'.(string)($route['public_addresses'][0]??'')]];
    }

    private function decodeResponse(array $response): array
    {
        $status = (int) ($response['status'] ?? 0);
        $contentType = strtolower((string) ($response['content_type'] ?? 'application/json'));
        $body = $response['body'] ?? '';
        if ($status < 200 || $status >= 300) {
            throw new ModuleAttachmentException('module_unavailable', 'O módulo recusou a solicitação.');
        }
        if (!str_starts_with($contentType, 'application/json')) {
            throw new ModuleAttachmentException('invalid_content_type', 'Resposta inválida do módulo.');
        }
        if (is_array($body)) return $body;
        try {
            $decoded = json_decode((string) $body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ModuleAttachmentException('invalid_json', 'JSON inválido retornado pelo módulo.');
        }
        if (!is_array($decoded)) throw new ModuleAttachmentException('invalid_json', 'JSON inválido retornado pelo módulo.');
        return $decoded;
    }
}
