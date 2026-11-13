<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Support\Dni;
use Illuminate\Foundation\Http\FormRequest;

class SearchClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Client::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('dni'))) {
            $this->merge(['dni' => Dni::normalize($this->input('dni'))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['dni' => ['required', 'string', 'regex:/^\d{13}$/']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['dni.regex' => 'La identidad debe tener 13 dígitos.'];
    }
}
