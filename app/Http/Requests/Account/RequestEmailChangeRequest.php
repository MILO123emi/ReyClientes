<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ConfirmedRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RequestEmailChangeRequest extends ConfirmedRequest
{
    public function authorize(): bool
    {
        return Gate::allows('change-own-email');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...$this->confirmationRules(), 'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.unique' => 'Ese correo ya lo usa una cuenta. Escribe uno distinto al actual.'];
    }
}
