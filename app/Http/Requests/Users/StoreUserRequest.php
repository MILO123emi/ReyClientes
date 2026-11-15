<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Http\Requests\ConfirmedRequest;
use App\Models\User;
use Illuminate\Support\Str;

class StoreUserRequest extends ConfirmedRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        } if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...$this->confirmationRules(), ...['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'], 'password' => $this->passwordRules()]];
    }
}
