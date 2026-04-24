<?php

namespace App\Http\Requests\Atencion;

use Illuminate\Foundation\Http\FormRequest;

class SeleccionarEntidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_entidad' => ['required', 'integer'],
        ];
    }
}
