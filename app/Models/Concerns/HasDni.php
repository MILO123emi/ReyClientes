<?php

namespace App\Models\Concerns;

use App\Support\Dni;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasDni
{
    protected static function bootHasDni(): void
    {
        static::saving(function (self $model): void {
            if ($model->isDirty('dni')) {
                $model->dni = Dni::normalize($model->dni);
                $model->dni_hash = Dni::hash($model->dni);
            }
        });
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function byDni(Builder $query, string $dni): void
    {
        $query->where('dni_hash', Dni::hash($dni));
    }

    public function maskedDni(): string
    {
        return Dni::mask($this->dni);
    }
}
