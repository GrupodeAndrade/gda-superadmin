<?php
declare(strict_types=1);
namespace Gda\Core\Http;

use Gda\ClientArea\ClientContext;
use PDO;

final class RequireClientContextMiddleware
{
    public function __construct(private PDO $db, private ClientContext $context) {}
    public function handle(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($_SESSION['auth_scope']??null)!=='client') throw new \RuntimeException('AUTH_REQUIRED');
        if (($user['role'] ?? '') === 'global_admin' || !$this->context->hasCompany()) throw new \RuntimeException('CLIENT_CONTEXT_REQUIRED');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM companies WHERE id=? AND status='active'");
        $stmt->execute([$this->context->companyId()]);
        if (!(int)$stmt->fetchColumn()) throw new \RuntimeException('CLIENT_CONTEXT_INACTIVE');
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE id=? AND company_id=? AND active=1 AND role IN (?,?)');
        $stmt->execute([(int)$user['id'], $this->context->companyId(), 'client_admin', 'operator']);
        if (!(int)$stmt->fetchColumn()) throw new \RuntimeException('CLIENT_CONTEXT_MISMATCH');
    }
}
