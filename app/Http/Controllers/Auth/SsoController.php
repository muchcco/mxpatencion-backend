<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SsoCallbackRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Services\Auth\ActiveSessionExistsException;
use App\Services\Auth\SsoAuthenticationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SsoController extends Controller
{
    public function __construct(
        private readonly SsoAuthenticationService $ssoAuthenticationService,
    ) {
    }

    public function callback(SsoCallbackRequest $request)
    {
        $payload = $request->validated();

        Log::info('SSO callback recibido.', [
            'mode' => isset($payload['rt']) ? 'rt' : 'token',
            'has_token' => filled($payload['token'] ?? null),
            'has_rt' => filled($payload['rt'] ?? null),
            'origin' => $request->headers->get('Origin'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            $advisor = $this->ssoAuthenticationService->authenticate(
                $payload,
                $request->session()
            );
        } catch (ActiveSessionExistsException $exception) {
            Log::warning('SSO callback rechazado por sesion activa existente.', [
                'mode' => isset($payload['rt']) ? 'rt' : 'token',
                'message' => $exception->getMessage(),
                'session_id' => $request->session()->getId(),
            ]);

            return $this->errorResponse(
                'Ya existe una sesion activa para este usuario. Cierra la sesion anterior antes de ingresar nuevamente.',
                [],
                409,
                [
                    'reason' => 'SESSION_ALREADY_ACTIVE',
                    'next_action' => 'BLOCK_ACCESS',
                ]
            );
        } catch (Throwable $throwable) {
            Log::warning('SSO callback fallo durante la autenticacion.', [
                'mode' => isset($payload['rt']) ? 'rt' : 'token',
                'message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        Log::info('SSO callback completo. Sesion creada correctamente.', [
            'id_usuario_externo' => $advisor->idUsuarioExterno,
            'login_usuario' => $advisor->loginUsuario,
            'id_region' => $advisor->idRegion,
            'session_id' => $request->session()->getId(),
        ]);

        return $this->successResponse([
            'user' => new AuthenticatedUserResource($advisor->toArray()),
            'roles' => $advisor->roles,
            'permissions' => $advisor->permissions,
            'region' => [
                'id_region' => $advisor->idRegion,
                'region' => $advisor->regionCodigo,
            ],
            'is_admin_app' => $advisor->isAdminApp,
            'paths' => $advisor->paths,
            'path_ids' => $advisor->pathIds,
        ], 'Sesion SSO inicializada correctamente.');
    }
}
