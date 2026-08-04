<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

final class ParticipantService
{
    public function accessToken(): string { return bin2hex(random_bytes(24)); }
    public function phoneDigits(string $phone): string { return preg_replace('/\D+/', '', $phone) ?? ''; }
}
