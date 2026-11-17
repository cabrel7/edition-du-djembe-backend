<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde de rôle défensive (les routes sont déjà protégées par auth:api + admin).
 * Même enveloppe d'erreur que le Handler : {success:false,message}.
 */
trait EnsuresAdminRole
{
    private function denyUnlessAdmin(): ?JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        if ((int) $user->role !== EnsureUserIsAdmin::ROLE_ADMIN) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
