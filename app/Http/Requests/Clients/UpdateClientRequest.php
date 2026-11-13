<?php

namespace App\Http\Requests\Clients;

use App\Http\Requests\CardholderRequest;
use App\Models\Client;
use App\Support\Dni;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends CardholderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('client')) ?? false;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        return $this->cardholderRules(false);
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('dni')) {
                return;
            }

            $query = Client::query()->byDni($this->string('dni')->toString());
            /** @var Client $client */
            $client = $this->route('client');
            $query->whereKeyNot($client->id);

            if ($client->authorizedPeople()->where('dni_hash', Dni::hash($this->string('dni')->toString()))->exists()) {
                $validator->errors()->add('dni', 'Una persona autorizada de este cliente ya tiene esta identidad.');
            }

            if ($query->exists()) {
                $validator->errors()->add('dni', 'Ya existe un cliente con esta identidad.');
            }
        }];
    }
}
