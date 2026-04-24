<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminService
{
    public function listarEntidades(): array
    {
        return DB::table('cfg.entidad')
            ->orderBy('orden_visual')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    public function crearEntidad(array $payload): array
    {
        $id = DB::table('cfg.entidad')->insertGetId(array_merge($payload, [
            'estado' => $payload['estado'] ?? 1,
            'fecha_creacion' => now(),
            'fecha_actualizacion' => now(),
        ]));

        return $this->obtenerEntidad($id);
    }

    public function actualizarEntidad(int $id, array $payload): array
    {
        DB::table('cfg.entidad')
            ->where('id_entidad', $id)
            ->update(array_merge($payload, ['fecha_actualizacion' => now()]));

        return $this->obtenerEntidad($id);
    }

    public function listarServicios(): array
    {
        return DB::table('cfg.servicio')
            ->orderBy('orden_visual')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    public function crearServicio(array $payload): array
    {
        $id = DB::table('cfg.servicio')->insertGetId(array_merge($payload, [
            'estado' => $payload['estado'] ?? 1,
            'requiere_retorno' => $payload['requiere_retorno'] ?? false,
            'abre_nueva_pestana' => $payload['abre_nueva_pestana'] ?? false,
            'fecha_creacion' => now(),
            'fecha_actualizacion' => now(),
        ]));

        return $this->obtenerServicio($id);
    }

    public function actualizarServicio(int $id, array $payload): array
    {
        DB::table('cfg.servicio')
            ->where('id_servicio', $id)
            ->update(array_merge($payload, ['fecha_actualizacion' => now()]));

        return $this->obtenerServicio($id);
    }

    public function listarEntidadesPorRegion(int $regionId): array
    {
        return DB::table('cfg.entidad_region as er')
            ->join('cfg.entidad as e', 'e.id_entidad', '=', 'er.id_entidad')
            ->where('er.id_region', $regionId)
            ->where('er.estado', 1)
            ->select('e.*', 'er.id_entidad_region', 'er.id_region')
            ->orderBy('e.orden_visual')
            ->orderBy('e.nombre')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    public function actualizarEntidadesPorRegion(int $regionId, array $entidadIds): array
    {
        DB::transaction(function () use ($regionId, $entidadIds): void {
            DB::table('cfg.entidad_region')
                ->where('id_region', $regionId)
                ->update([
                    'estado' => 0,
                    'fecha_actualizacion' => now(),
                ]);

            foreach (array_unique($entidadIds) as $entidadId) {
                DB::table('cfg.entidad_region')->updateOrInsert(
                    [
                        'id_region' => $regionId,
                        'id_entidad' => $entidadId,
                    ],
                    [
                        'estado' => 1,
                        'fecha_actualizacion' => now(),
                        'fecha_creacion' => now(),
                    ]
                );
            }
        });

        return $this->listarEntidadesPorRegion($regionId);
    }

    public function listarServiciosPorRegion(int $regionId): array
    {
        return DB::table('cfg.servicio as s')
            ->join('cfg.entidad_region as er', 'er.id_entidad', '=', 's.id_entidad')
            ->where('er.id_region', $regionId)
            ->where('er.estado', 1)
            ->where('s.estado', 1)
            ->select('s.*', 'er.id_region')
            ->orderBy('s.orden_visual')
            ->orderBy('s.nombre')
            ->get()
            ->map(fn ($item) => (array) $item)
            ->all();
    }

    public function actualizarServiciosPorRegion(int $regionId, array $servicioIds): array
    {
        $entidadIds = DB::table('cfg.servicio')
            ->whereIn('id_servicio', array_unique($servicioIds))
            ->pluck('id_entidad')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->actualizarEntidadesPorRegion($regionId, $entidadIds);

        return $this->listarServiciosPorRegion($regionId);
    }

    private function obtenerEntidad(int $id): array
    {
        $entidad = DB::table('cfg.entidad')->where('id_entidad', $id)->first();

        if ($entidad === null) {
            throw new RuntimeException('La entidad solicitada no existe.');
        }

        return (array) $entidad;
    }

    private function obtenerServicio(int $id): array
    {
        $servicio = DB::table('cfg.servicio')->where('id_servicio', $id)->first();

        if ($servicio === null) {
            throw new RuntimeException('El servicio solicitado no existe.');
        }

        return (array) $servicio;
    }
}
