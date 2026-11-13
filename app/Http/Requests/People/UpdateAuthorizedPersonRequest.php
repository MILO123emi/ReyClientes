<?php

namespace App\Http\Requests\People;

use App\Enums\Relationship;
use App\Http\Requests\CardholderRequest;
use App\Models\AuthorizedPerson;
use App\Support\Dni;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAuthorizedPersonRequest extends CardholderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('person')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...$this->cardholderRules(false),
            'relationship' => ['required', Rule::enum(Relationship::class)],
            'relationship_other' => ['nullable', 'required_if:relationship,otro', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), 'relationship_other.required_if' => 'Especifica el parentesco.'];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('dni')) {
                return;
            }

            /** @var AuthorizedPerson $person */
            $person = $this->route('person');
            $client = $person->client;
            $dni = $this->string('dni')->toString();
            if ($client->dni_hash === Dni::hash($dni)) {
                $validator->errors()->add('dni', 'La persona autorizada no puede tener la misma identidad que el titular.');
            }

            $query = $client->authorizedPeople()->byDni($dni);
            $query->whereKeyNot($person->id);
            if ($query->exists()) {
                $validator->errors()->add('dni', 'Esta persona ya está autorizada por este cliente.');
            }
        }];
    }
}
