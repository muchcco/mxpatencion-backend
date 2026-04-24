<?php

namespace App\Http\Requests\Ciudadano;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarCiudadanoManualRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tipoDocumento = $this->input('tipo_documento', $this->input('tipoDocumento'));

        $idTipoDocumento = $this->input(
            'id_tipo_documento',
            $this->input('idTipoDocumento', $this->input('tipoDocumentoId'))
        );

        if (is_array($tipoDocumento)) {
            $idTipoDocumento = $idTipoDocumento
                ?? ($tipoDocumento['id_tipo_documento'] ?? null)
                ?? ($tipoDocumento['idTipoDocumento'] ?? null)
                ?? ($tipoDocumento['id'] ?? null)
                ?? ($tipoDocumento['value'] ?? null);
        }

        $idTipoDocumento = $this->normalizeScalar($idTipoDocumento);

        $this->merge([
            'id_tipo_documento' => $idTipoDocumento,
            'numero_documento' => $this->input('numero_documento', $this->input('numeroDocumento')),
            'apellido_paterno' => $this->input('apellido_paterno', $this->input('apellidoPaterno')),
            'apellido_materno' => $this->input('apellido_materno', $this->input('apellidoMaterno')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tipo_documento' => ['required', 'integer'],
            'numero_documento' => ['required', 'string', 'max:30'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'sexo' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || strtolower($trimmed) === 'undefined' || strtolower($trimmed) === 'null') {
            return null;
        }

        return $trimmed;
    }
}
