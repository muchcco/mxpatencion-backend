<?php

namespace App\Services\Auth;

use App\DTOs\AuthenticatedUserData;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdvisorIdentityService
{
    public function sync(AuthenticatedUserData $advisor): void
    {
        try {
            DB::table('cfg.usuario_region_asesor')->updateOrInsert(
                [
                    'id_usuario_externo' => $advisor->idUsuarioExterno,
                ],
                array_filter([
                    'login_usuario' => $advisor->loginUsuario,
                    'nombre_usuario' => $advisor->nombreUsuario,
                    'id_region' => $advisor->idRegion,
                    'region' => $advisor->regionCodigo,
                ], static fn ($value) => $value !== null)
            );
        } catch (Throwable) {
            // Base extensible: si el modelo fisico difiere, no bloquea el login.
        }
    }
}
