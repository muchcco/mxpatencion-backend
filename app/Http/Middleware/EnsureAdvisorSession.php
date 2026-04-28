<?php

namespace App\Http\Middleware;

use App\Services\Auth\ActiveSessionExistsException;
use App\Services\Auth\SessionBootstrapService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdvisorSession
{
    public function __construct(
        private readonly SessionBootstrapService $sessionBootstrapService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('authenticated_advisor')) {
            return response()->json([
                'success' => false,
                'message' => 'La sesion del asesor no existe o ha expirado.',
                'data' => null,
                'errors' => [],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 401);
        }

        try {
            $this->sessionBootstrapService->assertSessionIsActive($request->session());
        } catch (ActiveSessionExistsException) {
            return response()->json([
                'success' => false,
                'message' => 'Esta cuenta ya tiene una sesion activa en otro dispositivo o ventana.',
                'data' => null,
                'errors' => [],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'reason' => 'SESSION_ALREADY_ACTIVE',
                    'next_action' => 'BLOCK_ACCESS',
                ],
            ], 409);
        }

        return $next($request);
    }
}
