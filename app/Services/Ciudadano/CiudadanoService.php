<?php

namespace App\Services\Ciudadano;

use App\DTOs\AuthenticatedUserData;
use App\Repositories\SqlServerStoredProcedureRepository;
use App\Services\Pide\PideCitizenNotFoundException;
use App\Services\Pide\PideService;
use App\Services\Pide\PideUnavailableException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CiudadanoService
{
    public function __construct(
        private readonly SqlServerStoredProcedureRepository $storedProcedureRepository,
        private readonly PideService $pideService,
    ) {
    }

    public function buscarLocal(
        int|string $idTipoDocumento,
        string $numeroDocumento,
        ?AuthenticatedUserData $advisor = null,
        array $context = []
    ): ?array {
        $ciudadano = null;

        try {
            $result = $this->storedProcedureRepository->select(
                'ope.usp_buscar_ciudadano_local',
                [
                    'id_tipo_documento' => $idTipoDocumento,
                    'numero_documento' => $numeroDocumento,
                ]
            );

            if ($result !== []) {
                $ciudadano = (array) $result[0];
            }
        } catch (Throwable) {
            $local = DB::table('ope.ciudadano')
                ->where('id_tipo_documento', $idTipoDocumento)
                ->where('numero_documento', $numeroDocumento)
                ->first();

            if ($local !== null) {
                $ciudadano = (array) $local;
            }
        }

        if ($advisor !== null) {
            $this->registrarAuditoriaConsulta(
                payload: [
                    'id_tipo_documento' => $idTipoDocumento,
                    'numero_documento' => $numeroDocumento,
                ],
                advisor: $advisor,
                context: $context,
                origenConsulta: 'LOCAL',
                resultado: $ciudadano !== null ? 'ENCONTRADO' : 'NO_ENCONTRADO',
                consumeCupoPide: false,
                ciudadano: $ciudadano,
                payloadResponse: $ciudadano,
            );
        }

        return $ciudadano;
    }

    public function consultarPide(array $payload, AuthenticatedUserData $advisor, array $context = []): ?array
    {
        try {
            $ciudadano = $this->pideService->consultarCiudadano(
                (string) $payload['id_tipo_documento'],
                $payload['numero_documento']
            );

            $this->registrarAuditoriaConsulta(
                payload: $payload,
                advisor: $advisor,
                context: $context,
                origenConsulta: 'PIDE',
                resultado: 'ENCONTRADO',
                consumeCupoPide: true,
                ciudadano: $ciudadano,
                payloadResponse: $ciudadano,
            );

            return $ciudadano;
        } catch (PideCitizenNotFoundException $exception) {
            $this->registrarAuditoriaConsulta(
                payload: $payload,
                advisor: $advisor,
                context: $context,
                origenConsulta: 'PIDE',
                resultado: 'NO_ENCONTRADO',
                consumeCupoPide: $exception->consumeCupo,
                payloadResponse: ['message' => $exception->getMessage()],
            );

            return null;
        } catch (PideUnavailableException $exception) {
            $this->registrarAuditoriaConsulta(
                payload: $payload,
                advisor: $advisor,
                context: $context,
                origenConsulta: 'PIDE',
                resultado: 'ERROR',
                consumeCupoPide: $exception->consumeCupo,
                mensajeError: $exception->getMessage(),
            );

            throw $exception;
        }
    }

    public function registrarManual(array $payload, ?AuthenticatedUserData $advisor = null, array $context = []): array
    {
        $ciudadano = $this->upsertManual($payload);

        if ($advisor !== null) {
            $this->registrarAuditoriaConsulta(
                payload: $payload,
                advisor: $advisor,
                context: $context,
                origenConsulta: 'MANUAL',
                resultado: 'ENCONTRADO',
                consumeCupoPide: false,
                ciudadano: $ciudadano,
                payloadResponse: $ciudadano,
            );
        }

        return $ciudadano;
    }

    public function obtenerPorId(int $id): ?array
    {
        $ciudadano = DB::table('ope.ciudadano')
            ->where('id_ciudadano', $id)
            ->first();

        return $ciudadano ? (array) $ciudadano : null;
    }

    private function upsertManual(array $payload): array
    {
        try {
            $result = $this->storedProcedureRepository->select(
                'ope.usp_upsert_ciudadano',
                $payload
            );

            if ($result !== []) {
                return (array) $result[0];
            }
        } catch (Throwable) {
            $id = DB::table('ope.ciudadano')->insertGetId($payload);

            $ciudadano = DB::table('ope.ciudadano')->where('id_ciudadano', $id)->first();

            if ($ciudadano !== null) {
                return (array) $ciudadano;
            }
        }

        throw new RuntimeException('No fue posible registrar el ciudadano manualmente.');
    }

    private function registrarAuditoriaConsulta(
        array $payload,
        AuthenticatedUserData $advisor,
        array $context,
        string $origenConsulta,
        string $resultado,
        bool $consumeCupoPide,
        ?array $ciudadano = null,
        ?array $payloadResponse = null,
        ?string $mensajeError = null,
    ): void {
        $auditPayload = [
            'id_tipo_documento' => $payload['id_tipo_documento'] ?? null,
            'numero_documento' => $payload['numero_documento'] ?? null,
            'origen_consulta' => $origenConsulta,
            'consume_cupo_pide' => $consumeCupoPide ? 1 : 0,
            'resultado' => $resultado,
            'id_ciudadano' => $ciudadano['id_ciudadano'] ?? null,
            'id_atencion' => $context['id_atencion'] ?? null,
            'id_usuario_externo' => $advisor->idUsuarioExterno,
            'login_usuario' => $advisor->loginUsuario,
            'nombre_usuario' => $advisor->nombreUsuario,
            'mensaje_error' => $mensajeError,
            'payload_request_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'payload_response_json' => $payloadResponse !== null ? json_encode($payloadResponse, JSON_UNESCAPED_UNICODE) : null,
            'fecha_consulta' => now(),
            'tiempo_respuesta_ms' => $context['tiempo_respuesta_ms'] ?? null,
            'ip_cliente' => $context['ip_cliente'] ?? null,
            'session_id' => $context['session_id'] ?? null,
        ];

        try {
            $this->storedProcedureRepository->statement(
                'aud.usp_registrar_consulta_identidad',
                $auditPayload
            );
        } catch (Throwable) {
            try {
                DB::table('aud.consulta_identidad')->insert($auditPayload);
            } catch (Throwable) {
                // No bloquea el flujo principal.
            }
        }
    }
}
