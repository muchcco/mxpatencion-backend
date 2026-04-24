<?php

namespace App\Services\AvisoOperativo;

use App\DTOs\AuthenticatedUserData;
use App\Repositories\SqlServerStoredProcedureRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AvisoOperativoService
{
    private const DEFAULT_LIMIT = 20;
    private const DEFAULT_RECENT_HOURS = 2;

    public function __construct(
        private readonly SqlServerStoredProcedureRepository $storedProcedureRepository,
    ) {
    }

    public function listarPorUsuario(AuthenticatedUserData $advisor, ?int $limit = null): array
    {
        $limit = $this->normalizeLimit($limit);
        $from = now()->subHours(self::DEFAULT_RECENT_HOURS);

        try {
            $items = array_map(
                static fn ($item) => (array) $item,
                $this->storedProcedureRepository->select(
                    'ope.usp_listar_avisos_operativos_por_usuario',
                    [
                        'id_usuario_externo' => $advisor->idUsuarioExterno,
                        'login_usuario' => $advisor->loginUsuario,
                        'id_region' => $advisor->idRegion,
                        'fecha_desde' => $from,
                    ]
                )
            );

            $items = array_values(array_filter(
                $items,
                fn (array $item) => $this->isRecent($item, $from)
            ));

            return array_slice($items, 0, $limit);
        } catch (Throwable) {
            return DB::table('ope.aviso_operativo_servicio')
                ->where('fecha_reporte', '>=', $from)
                ->orderByDesc('fecha_reporte')
                ->orderByDesc('id_aviso_operativo_servicio')
                ->limit($limit)
                ->get()
                ->map(fn ($item) => (array) $item)
                ->all();
        }
    }

    public function registrar(array $payload, AuthenticatedUserData $advisor): array
    {
        try {
            $result = $this->storedProcedureRepository->select(
                'ope.usp_registrar_aviso_operativo_servicio',
                array_merge($payload, [
                    'id_usuario_externo' => $advisor->idUsuarioExterno,
                    'login_usuario' => $advisor->loginUsuario,
                ])
            );

            if ($result !== []) {
                return (array) $result[0];
            }
        } catch (Throwable) {
            $id = DB::table('ope.aviso_operativo_servicio')->insertGetId(array_merge($payload, [
                'id_usuario_externo' => $advisor->idUsuarioExterno,
                'login_usuario' => $advisor->loginUsuario,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            return $this->obtener($id);
        }

        throw new RuntimeException('No fue posible registrar el aviso operativo.');
    }

    public function actualizarEstado(int $id, array $payload): array
    {
        try {
            $result = $this->storedProcedureRepository->select(
                'ope.usp_actualizar_estado_aviso_operativo_servicio',
                array_merge(['id_aviso_operativo_servicio' => $id], $payload)
            );

            if ($result !== []) {
                return (array) $result[0];
            }
        } catch (Throwable) {
            $updated = DB::table('ope.aviso_operativo_servicio')
                ->where('id_aviso_operativo_servicio', $id)
                ->update(array_merge($payload, ['updated_at' => now()]));

            if ($updated > 0 || DB::table('ope.aviso_operativo_servicio')->where('id_aviso_operativo_servicio', $id)->exists()) {
                return $this->obtener($id);
            }
        }

        throw new RuntimeException('No fue posible actualizar el estado del aviso operativo.');
    }

    private function obtener(int $id): array
    {
        $aviso = DB::table('ope.aviso_operativo_servicio')
            ->where('id_aviso_operativo_servicio', $id)
            ->first();

        if ($aviso === null) {
            throw new RuntimeException('El aviso operativo solicitado no existe.');
        }

        return (array) $aviso;
    }

    private function normalizeLimit(?int $limit): int
    {
        if ($limit === null || $limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, 100);
    }

    private function isRecent(array $item, Carbon $from): bool
    {
        $fechaReporte = $item['fecha_reporte'] ?? null;

        if ($fechaReporte === null) {
            return false;
        }

        try {
            return Carbon::parse((string) $fechaReporte)->greaterThanOrEqualTo($from);
        } catch (Throwable) {
            return false;
        }
    }
}
