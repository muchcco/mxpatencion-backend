<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegionEntidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entidad_ids' => ['required', 'array'],
            'entidad_ids.*' => ['integer'],
        ];
    }
}
