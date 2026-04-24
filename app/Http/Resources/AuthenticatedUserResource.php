<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'] ?? $this['id_usuario_externo'] ?? $this['idUsuarioExterno'] ?? null,
            'nombres' => $this['nombres'] ?? null,
            'apellidos' => $this['apellidos'] ?? null,
            'nombre_completo' => $this['nombre_completo'] ?? $this['nombre_usuario'] ?? $this['nombreUsuario'] ?? null,
            'email' => $this['email'] ?? null,
            'region_codigo' => $this['region_codigo'] ?? $this['region'] ?? $this['regionCodigo'] ?? null,
            'region_nombre' => $this['region_nombre'] ?? null,
            'sede_nombre' => $this['sede_nombre'] ?? null,
            'cargo' => $this['cargo'] ?? null,
            'roles' => $this['roles'] ?? [],
            'permissions' => $this['permissions'] ?? [],
        ];
    }
}
