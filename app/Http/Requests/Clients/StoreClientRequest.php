<?php

namespace App\Http\Requests\Clients;

use App\Http\Requests\CardholderRequest;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class StoreClientRequest extends CardholderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return $this->cardholderRules(true);
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('dni')) {
                return;
            }

            $query = Client::query()->byDni($this->string('dni')->toString());

            if ($query->exists()) {
                $validator->errors()->add('dni', 'Ya existe un cliente con esta identidad.');
            }
        }];
    }
}
