<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegionServiciosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servicio_ids' => ['required', 'array'],
            'servicio_ids.*' => ['integer'],
        ];
    }
}
