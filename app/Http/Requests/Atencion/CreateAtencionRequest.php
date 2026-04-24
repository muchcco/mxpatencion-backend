<?php

namespace App\Http\Requests\Atencion;

use Illuminate\Foundation\Http\FormRequest;

class CreateAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_ciudadano' => ['required', 'integer'],
            'id_estado_atencion' => ['nullable', 'integer'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
