<?php

namespace App\Services\Catalogo;

use App\DTOs\AuthenticatedUserData;
use App\Repositories\SqlServerStoredProcedureRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatalogoService
{
    public function __construct(
        private readonly SqlServerStoredProcedureRepository $storedProcedureRepository,
    ) {
    }

    public function listarTiposDocumento(): array
    {
        return DB::table('cfg.tipo_documento')
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    public function listarEntidadesVisibles(AuthenticatedUserData $advisor): array
    {
        try {
            $result = array_map(
                static fn ($item) => (array) $item,
                $this->storedProcedureRepository->select(
                    'cfg.usp_listar_entidades_visibles_por_usuario',
                    [
                        'id_usuario_externo' => $advisor->idUsuarioExterno,
                        'login_usuario' => $advisor->loginUsuario,
                        'id_region' => $advisor->idRegion,
                    ]
                )
            );

            if ($result !== []) {
                return $result;
            }
        } catch (Throwable) {
            // Continua con fallback semantico sobre tablas base.
        }

        return array_map(
            static fn ($item) => (array) $item,
            DB::table('cfg.entidad as e')
                ->join(
                    'cfg.tipo_alcance_entidad as tae',
                    'tae.id_tipo_alcance_entidad',
                    '=',
                    'e.id_tipo_alcance_entidad'
                )
                ->leftJoin('cfg.entidad_region as er', function ($join): void {
                    $join->on('er.id_entidad', '=', 'e.id_entidad')
                        ->where('er.estado', 1);
                })
                ->where('e.estado', 1)
                ->where('tae.estado', 1)
                ->where(function ($query) use ($advisor): void {
                    $query->where('tae.codigo', 'CENTRAL');

                    if ($advisor->idRegion !== null) {
                        $query->orWhere(function ($regionalQuery) use ($advisor): void {
                            $regionalQuery
                                ->whereIn('tae.codigo', ['REGIONAL', 'LOCAL'])
                                ->where('er.id_region', $advisor->idRegion);
                        });
                    }
                })
                ->select('e.*')
                ->distinct()
                ->orderBy('e.orden_visual')
                ->orderBy('e.nombre')
                ->get()
                ->all()
        );
    }

    public function listarServiciosPorEntidad(int $idEntidad): array
    {
        return DB::table('cfg.servicio')
            ->where('id_entidad', $idEntidad)
            ->where('estado', 1)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => $this->transformServicio((array) $item))
            ->all();
    }

    public function obtenerServicio(int $idServicio): ?array
    {
        $servicio = DB::table('cfg.servicio')
            ->where('id_servicio', $idServicio)
            ->where('estado', 1)
            ->first();

        return $servicio ? $this->transformServicio((array) $servicio) : null;
    }

    private function transformServicio(array $servicio): array
    {
        $urlServicio = $servicio['url_destino'] ?? null;
        $urlRetorno = $servicio['retorno_url'] ?? $this->buildDefaultReturnUrl();

        return array_merge($servicio, [
            'nombre_servicio' => $servicio['nombre'] ?? null,
            'url_servicio' => $urlServicio,
            'url_tramite' => $urlServicio,
            'url_externa' => $urlServicio,
            'enlace_servicio' => $urlServicio,
            'enlace' => $urlServicio,
            'link' => $urlServicio,
            'url_retorno' => $urlRetorno,
        ]);
    }

    private function buildDefaultReturnUrl(): string
    {
        return rtrim((string) config('app.frontend_url', 'http://localhost:4200'), '/').'/atencion/retorno';
    }
}
