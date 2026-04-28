<?php

namespace App\Services\Ciudadano;

use App\DTOs\AuthenticatedUserData;
use App\Repositories\SqlServerStoredProcedureRepository;
use App\Services\Pide\PideCitizenNotFoundException;
use App\Services\Pide\PideService;
use App\Services\Pide\PideUnavailableException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            $ciudadanoPide = $this->pideService->consultarCiudadano(
                (string) $payload['id_tipo_documento'],
                $payload['numero_documento']
            );
            $ciudadano = $this->upsertPide($ciudadanoPide);

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
        $payload['fuente_origen_inicial'] = 'MANUAL';
        $payload['fuente_ultima_actualizacion'] = 'MANUAL';

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
        $procedurePayload = $this->onlyStoredProcedureCiudadanoFields($payload);

        try {
            $result = $this->storedProcedureRepository->select(
                'ope.usp_upsert_ciudadano',
                $procedurePayload
            );

            if ($result !== []) {
                $ciudadano = (array) $result[0];
                $this->actualizarCamposExtendidos(
                    ciudadano: $ciudadano,
                    payload: $payload,
                    preserveFuenteOrigenInicial: true
                );

                return $this->buscarLocalSinAuditoria(
                    $payload['id_tipo_documento'],
                    $payload['numero_documento']
                ) ?? $ciudadano;
            }
        } catch (Throwable) {
            return $this->upsertDirecto($payload, preserveFuenteOrigenInicial: true);
        }

        throw new RuntimeException('No fue posible registrar el ciudadano manualmente.');
    }

    private function upsertPide(array $payload): array
    {
        $procedurePayload = $this->onlyStoredProcedureCiudadanoFields($payload);

        try {
            $result = $this->storedProcedureRepository->select(
                'ope.usp_upsert_ciudadano',
                $procedurePayload
            );

            if ($result !== []) {
                $ciudadano = (array) $result[0];
                $this->actualizarCamposExtendidos(
                    ciudadano: $ciudadano,
                    payload: $payload,
                    preserveFuenteOrigenInicial: true
                );

                return $this->buscarLocalSinAuditoria(
                    $payload['id_tipo_documento'],
                    $payload['numero_documento']
                ) ?? $ciudadano;
            }
        } catch (Throwable) {
            return $this->upsertDirecto($payload, preserveFuenteOrigenInicial: true);
        }

        throw new RuntimeException('No fue posible registrar el ciudadano obtenido desde PIDE.');
    }

    private function buscarLocalSinAuditoria(int|string $idTipoDocumento, string $numeroDocumento): ?array
    {
        $local = DB::table('ope.ciudadano')
            ->where('id_tipo_documento', $idTipoDocumento)
            ->where('numero_documento', $numeroDocumento)
            ->first();

        return $local !== null ? (array) $local : null;
    }

    private function actualizarCamposExtendidos(array $ciudadano, array $payload, bool $preserveFuenteOrigenInicial): void
    {
        $updates = $this->filtrarColumnasCiudadano($payload);
        unset($updates['id_tipo_documento'], $updates['numero_documento']);

        if ($updates === []) {
            return;
        }

        if ($preserveFuenteOrigenInicial && array_key_exists('fuente_origen_inicial', $updates)) {
            $fuenteActual = $ciudadano['fuente_origen_inicial'] ?? null;

            if (filled($fuenteActual)) {
                unset($updates['fuente_origen_inicial']);
            }
        }

        if ($updates === []) {
            return;
        }

        try {
            $query = DB::table('ope.ciudadano');

            if (isset($ciudadano['id_ciudadano'])) {
                $query->where('id_ciudadano', $ciudadano['id_ciudadano']);
            } else {
                $query
                    ->where('id_tipo_documento', $payload['id_tipo_documento'])
                    ->where('numero_documento', $payload['numero_documento']);
            }

            $query->update($updates);
        } catch (Throwable) {
            // Si la BD aún no tiene las columnas nuevas, no bloquea el flujo principal.
        }
    }

    private function upsertDirecto(array $payload, bool $preserveFuenteOrigenInicial): array
    {
        $persistablePayload = $this->filtrarColumnasCiudadano($payload);
        $existing = $this->buscarLocalSinAuditoria(
            $payload['id_tipo_documento'],
            $payload['numero_documento']
        );

        if ($existing !== null) {
            if ($preserveFuenteOrigenInicial && filled($existing['fuente_origen_inicial'] ?? null)) {
                unset($persistablePayload['fuente_origen_inicial']);
            }

            unset($persistablePayload['id_tipo_documento'], $persistablePayload['numero_documento']);

            if ($persistablePayload !== []) {
                DB::table('ope.ciudadano')
                    ->where('id_ciudadano', $existing['id_ciudadano'])
                    ->update($persistablePayload);
            }

            return $this->obtenerPorId((int) $existing['id_ciudadano']) ?? $existing;
        }

        $id = DB::table('ope.ciudadano')->insertGetId($persistablePayload);
        $ciudadano = DB::table('ope.ciudadano')->where('id_ciudadano', $id)->first();

        if ($ciudadano !== null) {
            return (array) $ciudadano;
        }

        throw new RuntimeException('No fue posible registrar el ciudadano.');
    }

    private function onlyStoredProcedureCiudadanoFields(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'id_tipo_documento',
            'numero_documento',
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'sexo',
        ]));
    }

    private function filtrarColumnasCiudadano(array $payload): array
    {
        return array_filter(
            $payload,
            fn (mixed $value, string $column): bool => $this->ciudadanoTieneColumna($column),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function ciudadanoTieneColumna(string $column): bool
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        try {
            return $cache[$column] = Schema::hasColumn('ope.ciudadano', $column);
        } catch (Throwable) {
            return $cache[$column] = true;
        }
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
