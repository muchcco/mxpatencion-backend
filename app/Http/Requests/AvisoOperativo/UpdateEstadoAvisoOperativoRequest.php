<?php

namespace App\Http\Requests\AvisoOperativo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEstadoAvisoOperativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_estado_aviso_operativo' => ['required', 'integer'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
