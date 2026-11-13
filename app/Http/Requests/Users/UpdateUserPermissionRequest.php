<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Http\Requests\ConfirmedRequest;

class UpdateUserPermissionRequest extends ConfirmedRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...$this->confirmationRules(), ...['can_manage_cards' => ['required', 'boolean']]];
    }
}
