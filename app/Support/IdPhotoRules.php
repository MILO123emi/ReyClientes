<?php

namespace App\Support;

final class IdPhotoRules
{
    /** @return list<string> */
    public static function create(): array
    {
        return ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];
    }

    /** @return list<string> */
    public static function update(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];
    }
}
