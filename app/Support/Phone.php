<?php

namespace App\Support;

final class Phone
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s-]+/', '', $phone) ?? '';
        $digits = ltrim($phone, '+');

        if (strlen($digits) === 11 && str_starts_with($digits, '504')) {
            return substr($digits, 3);
        }

        return $phone;
    }

    public static function format(string $phone): string
    {
        $phone = self::normalize($phone);

        return substr($phone, 0, 4).'-'.substr($phone, 4, 4);
    }
}
