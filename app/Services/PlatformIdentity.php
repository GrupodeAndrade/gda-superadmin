<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class PlatformIdentity
{
    public function __construct(
        private readonly string $uuid,
        private readonly string $publicUrl,
        private readonly string $privateKey,
        private readonly string $protocolVersion = '1.0',
        private readonly string $installationId = '',
    ) {}

    public static function load(PDO $database, ?array $runtime = null): self
    {
        $stored = (new PlatformIdentityStore($database))->loadOrCreate();
        $runtime ??= RuntimeConfig::load();
        $publicUrl = rtrim(trim((string) ($runtime['app_url'] ?? '')), '/');
        if ($publicUrl === '') {
            $statement = $database->prepare("SELECT setting_value FROM settings WHERE setting_key='app_url' LIMIT 1");
            $statement->execute();
            $publicUrl = rtrim(trim((string) ($statement->fetchColumn() ?: '')), '/');
        }

        return new self(
            $stored['platform_uuid'],
            $publicUrl,
            $stored['private_key_pem'],
            '1.0',
            $stored['installation_id'],
        );
    }

    public function assertConfigured(): void
    {
        if (!$this->validUuid($this->uuid) || !filter_var($this->publicUrl, FILTER_VALIDATE_URL) || !str_starts_with($this->publicUrl, 'https://') || openssl_pkey_get_private($this->privateKey) === false) {
            throw new RuntimeException('Identidade de integração da plataforma não configurada.');
        }
    }

    public function uuid(): string { $this->assertConfigured(); return $this->uuid; }
    public function publicUrl(): string { $this->assertConfigured(); return $this->publicUrl; }
    public function protocolVersion(): string { return $this->protocolVersion; }
    public function installationId(): string { return $this->installationId !== '' ? $this->installationId : $this->uuid(); }

    public function publicKey(): string
    {
        $this->assertConfigured();
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->privateKey));
        if (!is_array($details) || !is_string($details['key'] ?? null)) throw new RuntimeException('Chave pública indisponível.');
        return $details['key'];
    }

    public function encrypt(string $secret): string
    {
        if ($secret === '' || !openssl_public_encrypt($secret, $encrypted, $this->publicKey(), OPENSSL_PKCS1_OAEP_PADDING)) throw new RuntimeException('Falha ao proteger credencial.');
        return base64_encode($encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false || !openssl_private_decrypt($decoded, $plain, $this->privateKey, OPENSSL_PKCS1_OAEP_PADDING)) throw new RuntimeException('Credencial protegida indisponível.');
        return $plain;
    }

    public function signGlobalAccess(string $moduleUuid, int $lifetime = 240, ?int $now = null, ?string $jti = null): array
    {
        $this->assertConfigured();
        if (!$this->validUuid($moduleUuid)) throw new RuntimeException('UUID do módulo inválido.');
        $now ??= time();
        $lifetime = max(30, min(300, $lifetime));
        $claims = ['iss'=>$this->uuid,'aud'=>$moduleUuid,'role'=>'global_admin','jti'=>$jti ?: bin2hex(random_bytes(24)),'iat'=>$now,'exp'=>$now+$lifetime];
        $encode = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode(json_encode(['alg'=>'RS256','typ'=>'JWT'], JSON_THROW_ON_ERROR));
        $payload = $encode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if (!openssl_sign($header.'.'.$payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Falha ao assinar autorização.');
        return ['token'=>$header.'.'.$payload.'.'.$encode($signature),'claims'=>$claims];
    }

    private function validUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
