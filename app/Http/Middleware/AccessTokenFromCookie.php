<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Authentification par cookie httpOnly (SPA).
 *
 * Le token Passport est envoyé au navigateur dans un cookie httpOnly
 * (jamais lisible par JavaScript) : ce middleware le convertit en header
 * Authorization Bearer pour le garde auth:api, uniquement si aucun header
 * Authorization n'est déjà présent (les clients API non-browser gardent
 * l'usage classique du header).
 */
class AccessTokenFromCookie
{
    public const COOKIE_NAME = 'edj_token';

    public function handle(Request $request, Closure $next)
    {
        if (!$request->headers->has('Authorization')) {
            $token = $request->cookie(self::COOKIE_NAME);

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer ' . $token);
            }
        }

        return $next($request);
    }
}
