<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdvisorSession
{
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

        return $next($request);
    }
}
