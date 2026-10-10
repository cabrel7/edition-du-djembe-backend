<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public const ROLE_ADMIN = 1;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== self::ROLE_ADMIN) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux administrateurs.'
            ], 403);
        }

        return $next($request);
    }
}
