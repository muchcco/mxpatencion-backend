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
            $payload = $request->validated();
            $payload['id_tipo_documento'] = $request->input('id_tipo_documento', $request->input('tipo_documento'));
            $numeroDocumento = $request->string('numero_documento')->toString();

            if (preg_match('/^\d{8}$/', $numeroDocumento) === 1) {
                try {
                    $ciudadanoPide = $this->ciudadanoService->consultarPide($payload, $advisor, [
                        'ip_cliente' => $request->ip(),
                        'session_id' => $request->session()->getId(),
                    ]);

                    if ($ciudadanoPide !== null) {
                        return $this->successResponse(
                            new CiudadanoResource($ciudadanoPide),
                            'Ciudadano obtenido desde PIDE.',
                            200,
                            [
                                'source' => 'pide',
                                'found' => true,
                                'next_action' => 'SHOW_CITIZEN',
                            ]
                        );
                    }

                    return $this->successResponse(
                        null,
                        'El ciudadano no fue encontrado en PIDE. Puede registrarlo manualmente.',
                        200,
                        [
                            'source' => 'pide',
                            'found' => false,
                            'next_action' => 'ENABLE_MANUAL',
                            'alert' => [
                                'type' => 'warning',
                                'reason' => 'PIDE_NOT_FOUND',
                                'message' => 'PIDE respondió, pero no encontró datos para este DNI. Continúa con registro manual.',
                            ],
                        ]
                    );
                } catch (PideUnavailableException $exception) {
                    $alertMessage = $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'No se pudo consultar PIDE/RENIEC. Revisa la interconexión o credenciales; el registro manual queda habilitado.';

                    return $this->successResponse(
                        null,
                        "{$alertMessage} Puede registrar al ciudadano manualmente.",
                        200,
                        [
                            'source' => 'pide',
                            'available' => false,
                            'found' => false,
                            'next_action' => 'ENABLE_MANUAL',
                            'reason' => 'PIDE_UNAVAILABLE',
                            'alert' => [
                                'type' => 'error',
                                'reason' => 'PIDE_UNAVAILABLE',
                                'message' => "{$alertMessage} El registro manual queda habilitado.",
                            ],
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
                            'reason' => 'PIDE_ERROR',
                            'alert' => [
                                'type' => 'error',
                                'reason' => 'PIDE_ERROR',
                                'message' => $exception->getMessage(),
                            ],
                        ]
                    );
                }
            }

            return $this->successResponse(
                null,
                'El ciudadano no existe en la base local. Puede registrarlo manualmente.',
                200,
                [
                    'source' => 'local',
                    'found' => false,
                    'next_action' => 'ENABLE_MANUAL',
                ]
            );
        }

        $shouldRefreshFromPide = $this->ciudadanoRequiereActualizacionPide(
            $ciudadano,
            $request->string('numero_documento')->toString()
        );
        $pideRefreshAttempted = false;

        if ($shouldRefreshFromPide) {
            $pideRefreshAttempted = true;
            $payload = $request->validated();
            $payload['id_tipo_documento'] = $request->input('id_tipo_documento', $request->input('tipo_documento'));

            try {
                $ciudadanoPide = $this->ciudadanoService->consultarPide($payload, $advisor, [
                    'ip_cliente' => $request->ip(),
                    'session_id' => $request->session()->getId(),
                ]);

                if ($ciudadanoPide !== null) {
                    return $this->successResponse(
                        new CiudadanoResource($ciudadanoPide),
                        'Ciudadano actualizado desde PIDE.',
                        200,
                        [
                            'source' => 'pide',
                            'found' => true,
                            'next_action' => 'SHOW_CITIZEN',
                            'refreshed_from_pide' => true,
                        ]
                    );
                }
            } catch (PideUnavailableException | RuntimeException) {
                // Si PIDE sigue fallando, devolvemos el registro local manual para no bloquear la atencion.
            }
        }

        return $this->successResponse(
            new CiudadanoResource($ciudadano),
            'Ciudadano encontrado en la base local.',
            200,
            [
                'source' => 'local',
                'found' => true,
                'next_action' => $shouldRefreshFromPide && ! $pideRefreshAttempted ? 'CONSULT_PIDE_REFRESH_PHOTO' : 'SHOW_CITIZEN',
                'can_refresh_from_pide' => $shouldRefreshFromPide,
                'pide_refresh_attempted' => $pideRefreshAttempted,
            ]
        );
    }

    public function consultarPide(ConsultarPideRequest $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));
        $payload = $request->validated();
        $payload['id_tipo_documento'] = $request->input('id_tipo_documento', $request->input('tipo_documento'));
        $ciudadanoLocal = $this->ciudadanoService->buscarLocal(
            $payload['id_tipo_documento'],
            $payload['numero_documento'],
            $advisor,
            [
                'ip_cliente' => $request->ip(),
                'session_id' => $request->session()->getId(),
            ]
        );

        if ($ciudadanoLocal !== null && ! $this->ciudadanoRequiereActualizacionPide($ciudadanoLocal, $payload['numero_documento'])) {
            return $this->successResponse(
                new CiudadanoResource($ciudadanoLocal),
                'Ciudadano encontrado en la base local. No se consumió PIDE.',
                200,
                [
                    'source' => 'local',
                    'found' => true,
                    'next_action' => 'SHOW_CITIZEN',
                    'pide_skipped' => true,
                    'skip_reason' => 'LOCAL_CITIZEN_ALREADY_COMPLETE',
                ]
            );
        }

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
            $alertMessage = $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'No se pudo consultar PIDE/RENIEC. Revisa la interconexión o credenciales.';

            return $this->successResponse(
                null,
                "{$alertMessage} Puede registrar al ciudadano manualmente.",
                200,
                [
                    'source' => 'pide',
                    'available' => false,
                    'found' => false,
                    'next_action' => 'ENABLE_MANUAL',
                    'reason' => 'PIDE_UNAVAILABLE',
                    'alert' => [
                        'type' => 'error',
                        'reason' => 'PIDE_UNAVAILABLE',
                        'message' => "{$alertMessage} El registro manual queda habilitado.",
                    ],
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

    private function ciudadanoRequiereActualizacionPide(array $ciudadano, string $numeroDocumento): bool
    {
        if (preg_match('/^\d{8}$/', $numeroDocumento) !== 1) {
            return false;
        }

        $fuenteInicial = strtoupper((string) ($ciudadano['fuente_origen_inicial'] ?? ''));

        return $fuenteInicial === 'MANUAL'
            && blank($ciudadano['foto_path'] ?? null)
            && blank($ciudadano['foto_url'] ?? null);
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
