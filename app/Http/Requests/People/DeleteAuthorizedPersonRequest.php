<?php

namespace App\Http\Requests\People;

use App\Http\Requests\ConfirmedRequest;

class DeleteAuthorizedPersonRequest extends ConfirmedRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('person')) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return $this->confirmationRules();
    }
}
