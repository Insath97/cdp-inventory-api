<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Promote the httpOnly auth_token cookie (set at login) to the Authorization
 * header so the JWT guard can authenticate from it. This lets the frontend
 * avoid persisting the token anywhere JavaScript can read (localStorage or
 * non-httpOnly cookies), which is what makes it XSS-exfiltration-proof.
 *
 * An explicit Authorization header always wins — API clients and the
 * frontend's transient in-memory fallback keep working unchanged.
 */
class AuthTokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->headers->has('Authorization')) {
            $token = $request->cookie('auth_token');

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
