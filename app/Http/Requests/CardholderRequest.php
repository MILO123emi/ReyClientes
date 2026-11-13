<?php

namespace App\Http\Requests;

use App\Rules\HondurasDni;
use App\Support\Dni;
use App\Support\IdPhotoRules;
use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;

abstract class CardholderRequest extends ConfirmedRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['full_name', 'dni', 'phone', 'relationship_other'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }

            $normalized[$field] = match ($field) {
                'dni' => Dni::normalize($value),
                'phone' => Phone::normalize($value),
                'full_name' => preg_replace('/\s+/u', ' ', trim($value)),
                default => trim($value),
            };
        }

        $this->merge($normalized);
    }

    /** @return array<string, list<string|ValidationRule>> */
    protected function cardholderRules(bool $creating): array
    {
        return [
            ...$this->confirmationRules(),
            'full_name' => ['required', 'string', 'min:3', 'max:120', "regex:/^[\pL\s'\-]+$/u"],
            'dni' => ['required', 'string', new HondurasDni],
            'phone' => ['required', 'string', 'regex:/^[2-9]\d{7}$/'],
            'id_photo' => $creating ? IdPhotoRules::create() : IdPhotoRules::update(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'full_name.regex' => 'El nombre solo puede tener letras, espacios, apóstrofos y guiones.',
            'phone.regex' => 'El teléfono debe tener 8 dígitos y empezar con 2 a 9.',
        ];
    }
}
