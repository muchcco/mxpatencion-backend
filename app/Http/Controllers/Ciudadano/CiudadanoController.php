<?php

namespace App\Http\Controllers\Ciudadano;

use App\DTOs\AuthenticatedUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ciudadano\BuscarCiudadanoRequest;
use App\Http\Requests\Ciudadano\ConsultarPideRequest;
use App\Http\Requests\Ciudadano\RegistrarCiudadanoManualRequest;
use App\Http\Resources\CiudadanoResource;
use App\Services\Ciudadano\CiudadanoService;
use App\Services\Pide\PideUnavailableException;
use Illuminate\Http\Request;
use RuntimeException;

class CiudadanoController extends Controller
{
    public function __construct(
        private readonly CiudadanoService $ciudadanoService,
    ) {
    }

    public function buscar(BuscarCiudadanoRequest $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));
        $ciudadano = $this->ciudadanoService->buscarLocal(
            $request->input('id_tipo_documento', $request->input('tipo_documento')),
            $request->string('numero_documento')->toString(),
            $advisor,
            [
                'ip_cliente' => $request->ip(),
                'session_id' => $request->session()->getId(),
            ]
        );

        if ($ciudadano === null) {
            return $this->successResponse(
                null,
                'El ciudadano no existe en la base local. Continúa con consulta PIDE.',
                200,
                [
                    'source' => 'local',
                    'found' => false,
                    'next_action' => 'CONSULT_PIDE',
                ]
            );
        }

        return $this->successResponse(
            new CiudadanoResource($ciudadano),
            'Ciudadano encontrado en la base local.',
            200,
            [
                'source' => 'local',
                'found' => true,
                'next_action' => 'SHOW_CITIZEN',
            ]
        );
    }

    public function consultarPide(ConsultarPideRequest $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));
        $payload = $request->validated();
        $payload['id_tipo_documento'] = $request->input('id_tipo_documento', $request->input('tipo_documento'));

        try {
            $ciudadano = $this->ciudadanoService->consultarPide($payload, $advisor, [
                'ip_cliente' => $request->ip(),
                'session_id' => $request->session()->getId(),
            ]);

            if ($ciudadano === null) {
                return $this->successResponse(
                    null,
                    'El ciudadano no fue encontrado en PIDE. Puede registrarlo manualmente.',
                    200,
                    [
                        'source' => 'pide',
                        'found' => false,
                        'next_action' => 'ENABLE_MANUAL',
                    ]
                );
            }

            return $this->successResponse(
                new CiudadanoResource($ciudadano),
                'Ciudadano obtenido desde PIDE.',
                200,
                [
                    'source' => 'pide',
                    'found' => true,
                    'next_action' => 'SHOW_CITIZEN',
                ]
            );
        } catch (PideUnavailableException $exception) {
            return $this->successResponse(
                null,
                'PIDE no está disponible. Puede registrar al ciudadano manualmente.',
                200,
                [
                    'source' => 'pide',
                    'available' => false,
                    'found' => false,
                    'next_action' => 'ENABLE_MANUAL',
                    'reason' => 'PIDE_UNAVAILABLE',
                ]
            );
        } catch (RuntimeException $exception) {
            return $this->successResponse(
                null,
                $exception->getMessage(),
                200,
                [
                    'source' => 'pide',
                    'available' => false,
                    'found' => false,
                    'next_action' => 'ENABLE_MANUAL',
                    'reason' => 'PIDE_UNAVAILABLE',
                ]
            );
        }
    }

    public function manual(RegistrarCiudadanoManualRequest $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));
        $ciudadano = $this->ciudadanoService->registrarManual($request->validated(), $advisor, [
            'ip_cliente' => $request->ip(),
            'session_id' => $request->session()->getId(),
        ]);

        return $this->successResponse(
            new CiudadanoResource($ciudadano),
            'Ciudadano registrado correctamente.',
            201,
            [
                'source' => 'manual',
                'next_action' => 'SHOW_CITIZEN',
            ]
        );
    }

    public function show(int $id)
    {
        $ciudadano = $this->ciudadanoService->obtenerPorId($id);

        if ($ciudadano === null) {
            return $this->errorResponse('El ciudadano solicitado no existe.', [], 404);
        }

        return $this->successResponse(
            new CiudadanoResource($ciudadano),
            'Ciudadano obtenido correctamente.'
        );
    }
}
