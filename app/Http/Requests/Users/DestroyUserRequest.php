<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Http\Requests\ConfirmedRequest;

class DestroyUserRequest extends ConfirmedRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('user')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...$this->confirmationRules(), ...[]];
    }
}
