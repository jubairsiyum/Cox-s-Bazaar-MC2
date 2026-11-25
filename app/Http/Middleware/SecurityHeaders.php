<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Harden web responses with standard browser security headers and a
 * nonce-based Content-Security-Policy on authentication pages.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $isAuthRoute = $request->routeIs('login', 'password.*', 'verification.*');
        $nonce = $isAuthRoute ? Vite::useCspNonce() : '';

        $response = $next($request);

        if ($response instanceof HttpResponse) {
            $contentType = $response->headers->get('Content-Type');
            if ($contentType === null) {
                $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
            } elseif (
                str_starts_with(strtolower($contentType), 'text/html')
                && ! str_contains(strtolower($contentType), 'charset')
            ) {
                $response->headers->set('Content-Type', $contentType.'; charset=UTF-8');
            }
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set(
                'Content-Security-Policy',
                $isAuthRoute ? $this->csp($nonce) : "base-uri 'self'; object-src 'none'; frame-ancestors 'none'"
            );
        }

        return $response;
    }

    private function csp(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
