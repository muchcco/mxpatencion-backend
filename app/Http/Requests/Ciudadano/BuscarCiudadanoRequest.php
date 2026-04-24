<?php

namespace App\Http\Requests\Ciudadano;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class BuscarCiudadanoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tipoDocumento = $this->input('tipo_documento', $this->input('tipoDocumento'));

        $idTipoDocumento = $this->input(
            'id_tipo_documento',
            $this->input('idTipoDocumento', $this->input('tipoDocumentoId'))
        );
        $codigoTipoDocumento = is_string($tipoDocumento) ? $tipoDocumento : null;

        if (is_array($tipoDocumento)) {
            $idTipoDocumento = $idTipoDocumento
                ?? ($tipoDocumento['id_tipo_documento'] ?? null)
                ?? ($tipoDocumento['idTipoDocumento'] ?? null)
                ?? ($tipoDocumento['id'] ?? null)
                ?? ($tipoDocumento['value'] ?? null);

            $codigoTipoDocumento = $tipoDocumento['codigo'] ?? $tipoDocumento['code'] ?? $tipoDocumento['nombre'] ?? null;
        }

        $idTipoDocumento = $this->normalizeScalar($idTipoDocumento);
        $codigoTipoDocumento = $this->normalizeScalar($codigoTipoDocumento);

        $this->merge([
            'id_tipo_documento' => $idTipoDocumento,
            'tipo_documento' => $codigoTipoDocumento,
            'numero_documento' => $this->input('numero_documento', $this->input('numeroDocumento')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tipo_documento' => ['nullable', 'integer'],
            'tipo_documento' => ['nullable', 'string', 'max:20'],
            'numero_documento' => ['required', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! filled($this->input('id_tipo_documento')) && ! filled($this->input('tipo_documento'))) {
                Log::warning('Busqueda de ciudadano rechazada por payload incompleto.', [
                    'payload_keys' => array_keys($this->all()),
                    'payload' => $this->all(),
                ]);

                $validator->errors()->add(
                    'id_tipo_documento',
                    'Debe enviar id_tipo_documento o tipo_documento.'
                );
            }
        });
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
