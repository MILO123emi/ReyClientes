<?php

namespace App\Http\Requests;

use App\Actions\Auth\ConfirmActionPassword;
use Illuminate\Foundation\Http\FormRequest;

abstract class ConfirmedRequest extends FormRequest
{
    protected function passesAuthorization(): bool
    {
        if (! parent::passesAuthorization()) {
            return false;
        }

        $this->container->make(ConfirmActionPassword::class)->verify($this);

        return true;
    }

    /** @return array{current_password: list<string>} */
    protected function confirmationRules(): array
    {
        return ['current_password' => ['required', 'string']];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->safe()->except('current_password');
    }
}
