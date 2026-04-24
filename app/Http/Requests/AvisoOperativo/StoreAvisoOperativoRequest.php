<?php

namespace App\Http\Requests\AvisoOperativo;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvisoOperativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_entidad' => ['required', 'integer'],
            'id_servicio' => ['required', 'integer'],
            'id_tipo_falla_servicio' => ['required', 'integer'],
            'id_severidad_aviso_operativo' => ['required', 'integer'],
            'id_estado_aviso_operativo' => ['nullable', 'integer'],
            'detalle' => ['required', 'string', 'max:1000'],
            'id_region' => ['nullable', 'integer'],
        ];
    }
}
