<?php

namespace App\Services\Auth;

use App\Repositories\SqlServerStoredProcedureRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdvisorRegionResolverService
{
    public function __construct(
        private readonly SqlServerStoredProcedureRepository $storedProcedureRepository,
    ) {
    }

    public function resolve(string $idUsuarioExterno, string $loginUsuario): array
    {
        try {
            $result = $this->storedProcedureRepository->select(
                'cfg.usp_asignar_region_a_asesor',
                [
                    'id_usuario_externo' => $idUsuarioExterno,
                    'login_usuario' => $loginUsuario,
                ]
            );

            if ($result !== []) {
                $region = (array) $result[0];

                return [
                    'id_region' => isset($region['id_region']) ? (int) $region['id_region'] : null,
                    'region' => $region['region'] ?? $region['codigo_region'] ?? null,
                ];
            }
        } catch (Throwable) {
            // Continua con fallback a consulta directa.
        }

        try {
            $region = DB::table('cfg.usuario_region_asesor')
                ->where('id_usuario_externo', $idUsuarioExterno)
                ->orWhere('login_usuario', $loginUsuario)
                ->first();

            if ($region !== null) {
                return [
                    'id_region' => isset($region->id_region) ? (int) $region->id_region : null,
                    'region' => $region->region ?? $region->codigo_region ?? null,
                ];
            }
        } catch (Throwable) {
            // Mantiene respuesta nula y delega el manejo al flujo superior.
        }

        return [
            'id_region' => null,
            'region' => null,
        ];
    }
}
