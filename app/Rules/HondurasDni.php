<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HondurasDni implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{13}$/', $value)) {
            $fail('La identidad debe tener 13 dígitos.');
        } elseif ((int) substr($value, 0, 2) < 1 || (int) substr($value, 0, 2) > 18) {
            $fail('El departamento de la identidad no es válido.');
        } elseif ((int) substr($value, 4, 4) < 1900 || (int) substr($value, 4, 4) > (int) now()->format('Y')) {
            $fail('El año de la identidad no es válido.');
        }
    }
}
