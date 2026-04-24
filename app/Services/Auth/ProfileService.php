<?php

namespace App\Services\Auth;

use App\DTOs\AuthenticatedUserData;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    public function buildProfile(AuthenticatedUserData $advisor): array
    {
        $region = null;

        if ($advisor->idRegion !== null) {
            $region = DB::table('cfg.region')
                ->where('id_region', $advisor->idRegion)
                ->first();
        }

        [$nombres, $apellidos] = $this->splitName($advisor->nombreUsuario);

        return [
            'id' => is_numeric($advisor->idUsuarioExterno) ? (int) $advisor->idUsuarioExterno : $advisor->idUsuarioExterno,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombre_completo' => $advisor->nombreUsuario,
            'email' => $advisor->email,
            'region_codigo' => $advisor->regionCodigo ?? $region?->codigo,
            'region_nombre' => $region?->nombre,
            'sede_nombre' => null,
            'cargo' => null,
            'roles' => array_map([$this, 'normalizeRole'], $advisor->roles),
            'permissions' => $advisor->permissions,
        ];
    }

    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) <= 2) {
            return [$fullName, ''];
        }

        $apellidos = implode(' ', array_slice($parts, -2));
        $nombres = implode(' ', array_slice($parts, 0, -2));

        return [$nombres, $apellidos];
    }

    private function normalizeRole(string $role): string
    {
        return ucfirst(mb_strtolower(trim($role)));
    }
}
