<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Ressource introuvable : enveloppe JSON uniforme pour toute l'API.
        $this->renderable(function (ModelNotFoundException|NotFoundHttpException $e, Request $request): ?JsonResponse {
            if (!$this->isApiRequest($request)) {
                return null;
            }

            return new JsonResponse(['success' => false, 'message' => self::MESSAGE_NOT_FOUND], Response::HTTP_NOT_FOUND);
        });
    }

    private const MESSAGE_NOT_FOUND = 'Ressource non trouvée';

    private const MESSAGE_UNAUTHENTICATED = 'Non authentifié.';

    /** Une requête /api/* attend toujours du JSON, même sans en-tête Accept. */
    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $this->isApiRequest($request) || parent::shouldReturnJson($request, $e);
    }

    /**
     * 401 JSON pour l'API ; jamais de redirection vers une route 'login'
     * (inexistante dans cette API : provoquait un 500 HTML).
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->isApiRequest($request)) {
            return new JsonResponse(['success' => false, 'message' => self::MESSAGE_UNAUTHENTICATED], Response::HTTP_UNAUTHORIZED);
        }

        return parent::unauthenticated($request, $exception);
    }
}
