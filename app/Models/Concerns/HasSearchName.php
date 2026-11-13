<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasSearchName
{
    public static function normalizeSearchName(string $name): string
    {
        return Str::squish(Str::lower(Str::ascii($name)));
    }

    protected static function bootHasSearchName(): void
    {
        static::saving(function (self $model): void {
            $model->search_name = self::normalizeSearchName($model->full_name);
        });
    }
}
