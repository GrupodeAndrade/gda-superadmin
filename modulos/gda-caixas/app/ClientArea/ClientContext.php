<?php
declare(strict_types=1);
namespace Gda\ClientArea;

final class ClientContext
{
    public function __construct(private ?int $authenticatedCompanyId = null) {}
    public static function fromAuthenticatedSession(): self
    {
        $value = $_SESSION['user']['company_id'] ?? null;
        return new self(is_numeric($value) && (int)$value > 0 ? (int)$value : null);
    }
    public function companyId(): int
    {
        if (!$this->authenticatedCompanyId) throw new \LogicException('Contexto de empresa indisponível.');
        return $this->authenticatedCompanyId;
    }
    public function hasCompany(): bool { return $this->authenticatedCompanyId !== null; }
}
