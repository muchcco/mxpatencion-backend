<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:200'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'orden_visual' => ['nullable', 'integer'],
            'estado' => ['nullable', 'integer'],
            'id_tipo_alcance_entidad' => ['required', 'integer'],
        ];
    }
}
