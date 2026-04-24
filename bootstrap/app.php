<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureAdvisorSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);

        $middleware->alias([
            'advisor.session' => EnsureAdvisorSession::class,
            'admin.access' => EnsureAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $throwable, Request $request): ?Response {
            $isApiRequest = $request->expectsJson() || str_contains($request->getPathInfo(), '/api/');

            if (! $isApiRequest) {
                return null;
            }

            $status = 500;
            $errors = [];

            if ($throwable instanceof ValidationException) {
                $status = $throwable->status;
                $errors = $throwable->errors();
            } elseif ($throwable instanceof AuthenticationException) {
                $status = 401;
            }

            if (method_exists($throwable, 'getStatusCode')) {
                $status = $throwable->getStatusCode();
            }

            $message = app()->hasDebugModeEnabled()
                ? $throwable->getMessage()
                : 'Ocurrio un error interno al procesar la solicitud.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], $status);
        });
    })->create();
