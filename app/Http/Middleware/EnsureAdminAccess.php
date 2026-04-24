<?php

namespace App\Http\Middleware;

use App\DTOs\AuthenticatedUserData;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->session()->get('authenticated_advisor');

        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'La sesión autenticada no existe o ha expirado.',
                'data' => null,
                'errors' => [],
                'meta' => ['timestamp' => now()->toIso8601String()],
            ], 401);
        }

        $advisor = AuthenticatedUserData::fromSession($payload);
        $roles = array_map(static fn ($role) => mb_strtolower((string) $role), $advisor->roles);

        if (! in_array('administrador', $roles, true) && ! in_array('moderador', $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para acceder a configuración.',
                'data' => null,
                'errors' => [],
                'meta' => ['timestamp' => now()->toIso8601String()],
            ], 403);
        }

        return $next($request);
    }
}
