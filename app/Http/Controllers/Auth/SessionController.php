<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\Auth\ProfileService;
use App\Services\Auth\SessionBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionBootstrapService $sessionBootstrapService,
        private readonly ProfileService $profileService,
    ) {
    }

    public function me(Request $request)
    {
        $advisor = $this->sessionBootstrapService->getAuthenticatedAdvisor($request->session());

        if ($advisor === null) {
            Log::notice('/api/v1/me sin sesion autenticada.', [
                'session_id' => $request->session()->getId(),
                'origin' => $request->headers->get('Origin'),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('La sesion autenticada no existe o ha expirado.', [], 401);
        }

        Log::info('/api/v1/me reconocio la sesion autenticada.', [
            'id_usuario_externo' => $advisor->idUsuarioExterno,
            'login_usuario' => $advisor->loginUsuario,
            'session_id' => $request->session()->getId(),
        ]);

        return $this->successResponse(
            new AuthenticatedUserResource($this->profileService->buildProfile($advisor)),
            'Perfil obtenido correctamente.'
        );
    }

    public function logout(Request $request)
    {
        Log::info('Solicitud de cierre de sesion recibida.', [
            'session_id' => $request->session()->getId(),
        ]);

        $this->sessionBootstrapService->destroy($request->session());

        return $this->successResponse(null, 'Sesion cerrada correctamente.');
    }
}
