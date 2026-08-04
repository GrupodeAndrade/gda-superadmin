<?php
declare(strict_types=1);
namespace Gda\Core\Http;

final class SecurityHeadersMiddleware
{
    public function handle(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }
}
