<?php

namespace App\Http\Requests\Ciudadano;

class ConsultarPideRequest extends BuscarCiudadanoRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'numero_documento' => ['required', 'string', 'regex:/^\d{8}$/'],
        ]);
    }

    public function messages(): array
    {
        return [
            'numero_documento.regex' => 'La consulta RENIEC por PIDE solo acepta DNI de 8 dígitos.',
        ];
    }
}
