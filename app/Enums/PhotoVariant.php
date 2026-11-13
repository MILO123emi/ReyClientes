<?php

namespace App\Enums;

enum PhotoVariant: string
{
    case Full = 'full';
    case Thumb = 'thumb';

    public function column(): string
    {
        return match ($this) {
            self::Full => 'id_photo_path',
            self::Thumb => 'id_photo_thumb_path',
        };
    }
}
