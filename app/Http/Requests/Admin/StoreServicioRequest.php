<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_entidad' => ['required', 'integer'],
            'codigo' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:200'],
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
