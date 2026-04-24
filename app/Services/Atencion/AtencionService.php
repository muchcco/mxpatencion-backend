<?php

namespace App\Services\Atencion;

use App\DTOs\AuthenticatedUserData;
use App\Services\Catalogo\CatalogoService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AtencionService
{
    public function __construct(
        private readonly CatalogoService $catalogoService,
    ) {
    }

    public function crear(array $payload, AuthenticatedUserData $advisor): array
    {
        $idEstadoAtencion = $payload['id_estado_atencion'] ?? $this->resolveInitialStatusId();

        $data = [
            'token_atencion' => (string) Str::uuid(),
            'codigo_atencion' => strtoupper(Str::random(12)),
            'id_ciudadano' => $payload['id_ciudadano'],
            'id_estado_atencion' => $idEstadoAtencion,
            'id_usuario_externo' => $advisor->idUsuarioExterno,
            'login_operador' => $advisor->loginUsuario,
            'nombre_operador' => $advisor->nombreUsuario,
            'operador_login' => $advisor->loginUsuario,
            'operador_nombre' => $advisor->nombreUsuario,
            'origen_registro' => 'MAC_EXPRESS',
            'fecha_registro' => now(),
            'fecha_ingreso_datos' => now(),
            'observacion' => $payload['observacion'] ?? null,
        ];

        $id = DB::table('ope.atencion')->insertGetId($data);

        return $this->obtener($id);
    }

    public function iniciar(int $id, array $payload = []): array
    {
        return $this->actualizar($id, array_merge($payload, ['fecha_inicio_atencion' => now()]));
    }

    public function seleccionarEntidad(int $id, int $idEntidad): array
    {
        $atencionActual = $this->obtener($id);

        $payload = [
            'id_entidad' => $idEntidad,
            'fecha_seleccion_entidad' => now(),
            'id_estado_atencion' => $this->resolveStatusIdByCode('EN_SELECCION'),
        ];

        $cambioEntidad = isset($atencionActual['id_entidad'])
            && (int) $atencionActual['id_entidad'] !== $idEntidad;

        if ($cambioEntidad && ! empty($atencionActual['id_servicio'])) {
            $payload = array_merge($payload, [
                'id_servicio' => null,
                'fecha_seleccion_servicio' => null,
                'fecha_salida_servicio' => null,
                'fecha_retorno_servicio' => null,
            ]);
        }

        if (
            ! $cambioEntidad
            && isset($atencionActual['id_entidad'])
            && (int) $atencionActual['id_entidad'] === $idEntidad
            && ! empty($atencionActual['id_servicio'])
        ) {
            $payload['id_estado_atencion'] = $this->resolveStatusIdByCode('EN_SERVICIO');
        }

        return $this->actualizar($id, $payload);
    }

    public function seleccionarServicio(int $id, int $idServicio): array
    {
        $atencionActual = $this->obtener($id);
        $idEntidadActual = isset($atencionActual['id_entidad']) ? (int) $atencionActual['id_entidad'] : null;

        if ($idEntidadActual === null) {
            throw new ConflictHttpException('Debe seleccionar una entidad antes de seleccionar un servicio.');
        }

        $this->assertServiceBelongsToEntity($idServicio, $idEntidadActual);

        $atencion = $this->actualizar($id, [
            'id_servicio' => $idServicio,
            'fecha_seleccion_servicio' => now(),
            'id_estado_atencion' => $this->resolveStatusIdByCode('EN_SERVICIO'),
        ]);

        $servicio = $this->catalogoService->obtenerServicio($idServicio);

        return array_merge($atencion, [
            'url_servicio' => $servicio['url_servicio'] ?? null,
            'url_tramite' => $servicio['url_tramite'] ?? null,
            'url_externa' => $servicio['url_externa'] ?? null,
            'enlace_servicio' => $servicio['enlace_servicio'] ?? null,
            'enlace' => $servicio['enlace'] ?? null,
            'link' => $servicio['link'] ?? null,
            'url_retorno' => $servicio['url_retorno'] ?? null,
        ]);
    }

    public function registrarRetorno(int $id, array $payload = []): array
    {
        return $this->actualizar($id, array_merge($payload, ['fecha_retorno_servicio' => now()]));
    }

    public function finalizar(int $id, array $payload = []): array
    {
        return $this->actualizar($id, array_merge($payload, ['fecha_fin_atencion' => now()]));
    }

    public function obtener(int $id): array
    {
        $atencion = DB::table('ope.atencion')->where('id_atencion', $id)->first();

        if ($atencion === null) {
            throw new RuntimeException('La atencion solicitada no existe.');
        }

        return (array) $atencion;
    }

    private function actualizar(int $id, array $data): array
    {
        $updated = DB::table('ope.atencion')
            ->where('id_atencion', $id)
            ->update($data);

        if ($updated === 0 && DB::table('ope.atencion')->where('id_atencion', $id)->doesntExist()) {
            throw new RuntimeException('La atencion solicitada no existe.');
        }

        return $this->obtener($id);
    }

    private function resolveInitialStatusId(): int
    {
        return $this->resolveStatusIdByCode('REGISTRADA');
    }

    private function resolveStatusIdByCode(string $code): int
    {
        $status = DB::table('cfg.estado_atencion')
            ->where('codigo', $code)
            ->where('estado', 1)
            ->value('id_estado_atencion');

        if ($status === null) {
            throw new RuntimeException("No existe un estado de atención configurado con código {$code}.");
        }

        return (int) $status;
    }

    private function assertServiceBelongsToEntity(int $idServicio, int $idEntidad): void
    {
        $exists = DB::table('cfg.servicio')
            ->where('id_servicio', $idServicio)
            ->where('id_entidad', $idEntidad)
            ->where('estado', 1)
            ->exists();

        if (! $exists) {
            throw new UnprocessableEntityHttpException(
                'El servicio seleccionado no pertenece a la entidad activa de la atención.'
            );
        }
    }
}
