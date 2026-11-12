<?php

namespace App\Support;

use RuntimeException;

final class Dni
{
    public static function normalize(string $dni): string
    {
        return preg_replace('/\D/', '', $dni) ?? '';
    }

    public static function hash(string $dni): string
    {
        $key = config('reypass.dni_hash_key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('DNI_HASH_KEY no está configurada.');
        }

        return hash_hmac('sha256', self::normalize($dni), $key);
    }

    public static function format(string $dni): string
    {
        $dni = self::normalize($dni);

        return substr($dni, 0, 4).'-'.substr($dni, 4, 4).'-'.substr($dni, 8, 5);
    }

    public static function mask(string $dni): string
    {
        return '****-****-*'.substr(self::normalize($dni), -4);
    }
}
