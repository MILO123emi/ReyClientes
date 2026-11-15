<?php

namespace App\Support;

final class IdPhotoRules
{
    /** Every raster format GD can decode; SVG and HEIC are not supported. */
    private const FORMATS = 'mimes:jpg,jpeg,png,webp,gif,bmp,avif';

    /** @return list<string> */
    public static function create(): array
    {
        return ['required', self::FORMATS, 'max:8192'];
    }

    /** @return list<string> */
    public static function update(): array
    {
        return ['nullable', self::FORMATS, 'max:8192'];
    }
}
