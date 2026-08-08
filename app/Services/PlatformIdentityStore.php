<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

final class PlatformIdentityStore
{
    public function __construct(private readonly PDO $database) {}

    public function loadOrCreate(): array
    {
        $stored = $this->find();
        if ($stored !== null) return $stored;

        $identity = $this->legacyIdentity() ?? $this->generate();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO platform_identity (id,platform_uuid,installation_id,private_key_pem,public_key_pem,created_at,updated_at) VALUES (1,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)'
            );
            $statement->execute([
                $identity['platform_uuid'],
                $identity['installation_id'],
                $identity['private_key_pem'],
                $identity['public_key_pem'],
            ]);
        } catch (PDOException $exception) {
            // Outra requisição pode ter criado a identidade entre o SELECT e o INSERT.
            if ($this->find() === null) throw new RuntimeException('Não foi possível persistir a identidade técnica.', 0, $exception);
        }

        return $this->find() ?? throw new RuntimeException('A identidade técnica persistida está indisponível.');
    }

    private function find(): ?array
    {
        $statement = $this->database->query('SELECT platform_uuid,installation_id,private_key_pem,public_key_pem FROM platform_identity WHERE id=1 LIMIT 1');
        $stored = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($stored)) return null;
        if (!$this->validUuid((string) $stored['platform_uuid']) || !$this->validInstallationId((string) $stored['installation_id'])) {
            throw new RuntimeException('A identidade técnica persistida é inválida.');
        }
        $key = openssl_pkey_get_private((string) $stored['private_key_pem']);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (!is_array($details) || !is_string($details['key'] ?? null) || !hash_equals(trim($details['key']), trim((string) $stored['public_key_pem']))) {
            throw new RuntimeException('O par de chaves da identidade técnica é inválido.');
        }
        return $stored;
    }

    private function generate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false || !openssl_pkey_export($key, $privateKey)) {
            throw new RuntimeException('Não foi possível gerar a identidade técnica.');
        }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !is_string($details['key'] ?? null)) {
            throw new RuntimeException('Não foi possível obter a chave pública da plataforma.');
        }
        return [
            'platform_uuid' => $this->uuidV4(),
            'installation_id' => $this->uuidV4(),
            'private_key_pem' => $privateKey,
            'public_key_pem' => $details['key'],
        ];
    }

    private function legacyIdentity(): ?array
    {
        $uuid = trim((string) (getenv('GDA_PLATFORM_UUID') ?: ''));
        $installationId = trim((string) (getenv('GDA_PLATFORM_INSTALLATION_ID') ?: ''));
        $privateKey = str_replace('\\n', "\n", trim((string) (getenv('GDA_PLATFORM_PRIVATE_KEY') ?: '')));
        if ($uuid === '' && $installationId === '' && $privateKey === '') return null;
        if (!$this->validUuid($uuid) || !$this->validInstallationId($installationId)) return null;
        $key = openssl_pkey_get_private($privateKey);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (!is_array($details) || !is_string($details['key'] ?? null)) return null;
        return [
            'platform_uuid' => $uuid,
            'installation_id' => $installationId,
            'private_key_pem' => $privateKey,
            'public_key_pem' => $details['key'],
        ];
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }

    private function validUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private function validInstallationId(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._:-]{8,190}$/', $value) === 1;
    }
}
