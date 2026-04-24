<?php

namespace App\Http\Requests\Admin;

class UpdateEntidadRequest extends StoreEntidadRequest
{
    public function rules(): array
    {
        return [
            'codigo' => ['sometimes', 'string', 'max:50'],
            'nombre' => ['sometimes', 'string', 'max:200'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'orden_visual' => ['nullable', 'integer'],
            'estado' => ['nullable', 'integer'],
            'id_tipo_alcance_entidad' => ['sometimes', 'integer'],
        ];
    }
}
