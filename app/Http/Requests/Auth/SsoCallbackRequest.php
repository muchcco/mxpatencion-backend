<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SsoCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string'],
            'rt' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasToken = filled($this->input('token'));
            $hasReferenceToken = filled($this->input('rt'));

            if ($hasToken === $hasReferenceToken) {
                $validator->errors()->add(
                    'token',
                    'Debe enviar exactamente uno de estos campos: token o rt.'
                );
            }
        });
    }
}
