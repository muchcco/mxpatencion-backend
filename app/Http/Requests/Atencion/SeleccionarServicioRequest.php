<?php

namespace App\Http\Requests\Atencion;

use Illuminate\Foundation\Http\FormRequest;

class SeleccionarServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_servicio' => ['required', 'integer'],
        ];
    }
}
