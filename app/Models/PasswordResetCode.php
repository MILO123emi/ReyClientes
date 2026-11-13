<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property CarbonImmutable $expires_at
 * @property int $attempts
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'code_hash', 'expires_at', 'attempts', 'used_at'])]
#[Hidden(['code_hash'])]
class PasswordResetCode extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'created_at' => 'datetime', 'attempts' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now()->subDay());
    }
}
