<?php

namespace App\Http\Requests\Recovery;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOwnerResetCodeRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'password' => $this->passwordRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.required' => 'Escribe el código de recuperación.', 'code.regex' => 'El código debe tener 6 dígitos.'];
    }
}
