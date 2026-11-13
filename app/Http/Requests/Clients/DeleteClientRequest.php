<?php

namespace App\Http\Requests\Clients;

use App\Http\Requests\ConfirmedRequest;

class DeleteClientRequest extends ConfirmedRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('client')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->confirmationRules();
    }
}
