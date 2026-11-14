<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the login page behind HTTP Basic Authentication.
 *
 * The browser performs a native credential check before the login form is ever
 * served, adding a first line of defence against automated bots and credential
 * stuffing. Credentials are configured via AUTH_GATE_USERNAME / AUTH_GATE_PASSWORD;
 * when either is empty the gate is disabled.
 */
class BrowserAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('auth.browser_gate.username', '');
        $password = (string) config('auth.browser_gate.password', '');

        if ($username === '' || $password === '') {
            return $next($request);
        }

        $valid = hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());

        if ($valid) {
            return $next($request);
        }

        return response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Restricted Access", charset="UTF-8"',
        ]);
    }
}
