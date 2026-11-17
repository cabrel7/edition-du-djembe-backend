<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Idempotence des POST de création via l'en-tête `Idempotency-Key: <uuid>`.
 *
 * - Sans en-tête, ou avec un `id` (mise à jour) : requête inchangée.
 * - Seules les réponses 2xx JSON sont mémorisées (les 4xx/5xx restent rejouables).
 * - Concurrence : la 2e requête attend le verrou `lock_wait_seconds` puis rejoue
 *   la réponse mémorisée ; au-delà, 409 + Retry-After.
 * - Toute panne du cache laisse passer la requête (warning, jamais de 500).
 */
class IdempotentRequest
{
    public const HEADER = 'Idempotency-Key';
    public const REPLAY_HEADER = 'Idempotent-Replayed';
    public const MAX_KEY_LENGTH = 64;
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private const PREFIX = 'idem:';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        // Sans utilisateur authentifié, aucune clé fiable : pas de mémorisation partagée entre anonymes.
        if ($key === null || $key === '' || $request->filled('id') || $request->user() === null) {
            return $next($request);
        }

        if (!is_string($key) || strlen($key) > self::MAX_KEY_LENGTH || !preg_match(self::UUID_PATTERN, $key)) {
            return new JsonResponse(['success' => false, 'message' => 'En-tête Idempotency-Key invalide (UUID attendu)'], 400);
        }

        $hash = hash('sha256', implode('|', [
            $request->user()->getAuthIdentifier(),
            $request->method(),
            $request->path(),
            $key,
        ]));
        $fingerprint = $this->fingerprint($request);

        $lock = null;
        try {
            $lock = Cache::lock(self::PREFIX . 'lock:' . $hash, (int) config('idempotency.lock_seconds'));
            $acquired = $this->acquire($lock);

            $stored = Cache::get(self::PREFIX . 'resp:' . $hash);
            if (is_array($stored)) {
                if ($acquired) {
                    $this->release($lock);
                }

                return $this->replay($stored, $fingerprint);
            }

            if (!$acquired) {
                return new JsonResponse(
                    ['success' => false, 'message' => 'Requête en cours de traitement'],
                    409,
                    ['Retry-After' => (string) config('idempotency.retry_after_seconds')]
                );
            }
        } catch (Throwable $e) {
            Log::warning('Idempotence indisponible, requête exécutée sans protection', ['error' => $e->getMessage()]);
            $this->release($lock);

            return $next($request);
        }

        try {
            $response = $next($request);
            $this->remember($response, $hash, $fingerprint);

            return $response;
        } finally {
            $this->release($lock);
        }
    }

    private function acquire(Lock $lock): bool
    {
        $wait = (int) config('idempotency.lock_wait_seconds');

        if ($lock->get()) {
            return true;
        }

        if ($wait <= 0) {
            return false;
        }

        try {
            return (bool) $lock->block($wait);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return false;
        }
    }

    private function release(?Lock $lock): void
    {
        try {
            $lock?->release();
        } catch (Throwable $e) {
            Log::warning('Idempotence : libération du verrou impossible', ['error' => $e->getMessage()]);
        }
    }

    private function remember(Response $response, string $hash, string $fingerprint): void
    {
        $status = $response->getStatusCode();
        $content = $response->getContent();

        if ($status < 200 || $status >= 300 || !is_string($content)) {
            return;
        }

        // Réponse trop volumineuse : non mémorisée (le cache n'est pas un entrepôt de blobs).
        if (strlen($content) > (int) config('idempotency.max_body_bytes')) {
            return;
        }

        try {
            Cache::put(self::PREFIX . 'resp:' . $hash, [
                'status' => $status,
                'body' => $content,
                'content_type' => $response->headers->get('Content-Type', 'application/json'),
                'fingerprint' => $fingerprint,
            ], (int) config('idempotency.ttl_seconds'));
        } catch (Throwable $e) {
            Log::warning('Idempotence : mémorisation impossible', ['error' => $e->getMessage()]);
        }
    }

    /** @param array<string, mixed> $stored */
    private function replay(array $stored, string $fingerprint): Response
    {
        if (!hash_equals((string) $stored['fingerprint'], $fingerprint)) {
            return new JsonResponse(
                ['success' => false, 'message' => 'Idempotency-Key déjà utilisée avec une requête différente'],
                422
            );
        }

        return new Response((string) $stored['body'], (int) $stored['status'], [
            'Content-Type' => $stored['content_type'],
            self::REPLAY_HEADER => 'true',
        ]);
    }

    /** Empreinte : méthode + chemin + corps non-fichier (trié) + nom/taille des fichiers. */
    private function fingerprint(Request $request): string
    {
        $input = $request->input();
        $this->sortRecursive($input);

        $files = [];
        foreach ($this->flattenFiles($request->allFiles()) as $name => $file) {
            $files[$name] = [$file->getClientOriginalName(), $file->getSize()];
        }
        ksort($files);

        return hash('sha256', json_encode([$request->method(), $request->path(), $input, $files]) ?: '');
    }

    /** @param array<mixed> $data */
    private function sortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }

    /**
     * @param array<mixed> $files
     * @return array<string, UploadedFile>
     */
    private function flattenFiles(array $files, string $prefix = ''): array
    {
        $out = [];
        foreach ($files as $name => $file) {
            $path = $prefix . $name;
            if (is_array($file)) {
                $out += $this->flattenFiles($file, $path . '.');
            } elseif ($file instanceof UploadedFile) {
                $out[$path] = $file;
            }
        }

        return $out;
    }
}
