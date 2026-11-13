<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ConfirmEmailChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('change-own-email');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', $this->input('code'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/^\d{6}$/']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.required' => 'Escribe el código de 6 dígitos.', 'code.regex' => 'El código tiene 6 dígitos.'];
    }
}
