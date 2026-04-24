<?php

namespace App\Http\Requests\Admin;

class UpdateServicioRequest extends StoreServicioRequest
{
    public function rules(): array
    {
        return [
            'id_entidad' => ['sometimes', 'integer'],
            'codigo' => ['sometimes', 'string', 'max:50'],
            'nombre' => ['sometimes', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'url_destino' => ['nullable', 'string', 'max:1000'],
            'tipo_destino' => ['nullable', 'string', 'max:50'],
            'requiere_retorno' => ['nullable', 'boolean'],
            'retorno_url' => ['nullable', 'string', 'max:1000'],
            'abre_nueva_pestana' => ['nullable', 'boolean'],
            'icono_url' => ['nullable', 'string', 'max:1000'],
            'orden_visual' => ['nullable', 'integer'],
            'estado' => ['nullable', 'integer'],
        ];
    }
}
