<?php

namespace App\Http\Requests\Recovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SendOwnerResetCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email', $this->session()->get('recovery.email'));
        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }
}
