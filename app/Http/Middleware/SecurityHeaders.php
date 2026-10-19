<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Durée HSTS en secondes (1 an), sans preload : engagement trop fort.
     */
    private const HSTS_MAX_AGE = 31536000;

    /**
     * Durée de cache des images statiques servies par PHP (7 jours).
     */
    private const IMAGE_CACHE_MAX_AGE = 604800;

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];

    private const JSON_CONTENT_TYPE_MARKER = 'json';

    private const HTML_CONTENT_TYPE_PREFIX = 'text/html';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                sprintf('max-age=%d; includeSubDomains', self::HSTS_MAX_AGE)
            );
        }

        if ($this->isCacheableImage($request, $response)) {
            $response->headers->set(
                'Cache-Control',
                sprintf('public, max-age=%d, immutable', self::IMAGE_CACHE_MAX_AGE)
            );
        }

        return $response;
    }

    /**
     * Image servie par le routeur Laravel : cache public long uniquement si la
     * réponse est vraiment une image (2xx). Les réponses JSON/HTML restent
     * intouchées pour ne jamais mettre de cache public sur l'API.
     */
    private function isCacheableImage(Request $request, Response $response): bool
    {
        if (! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if (str_contains($contentType, self::JSON_CONTENT_TYPE_MARKER)) {
            return false;
        }

        if (str_starts_with($contentType, 'image/')) {
            return true;
        }

        if (str_starts_with($contentType, self::HTML_CONTENT_TYPE_PREFIX)) {
            return false;
        }

        return $this->hasImageExtension($request);
    }

    private function hasImageExtension(Request $request): bool
    {
        $extension = strtolower(pathinfo($request->getPathInfo(), PATHINFO_EXTENSION));

        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }
}
