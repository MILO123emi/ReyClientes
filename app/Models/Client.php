<?php

namespace App\Models;

use App\Models\Concerns\HasDni;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $full_name
 * @property string $dni
 * @property string $dni_hash
 * @property string $phone
 * @property string $id_photo_path
 * @property string $id_photo_thumb_path
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read Collection<int, AuthorizedPerson> $authorizedPeople
 */
#[Fillable(['full_name', 'dni', 'phone', 'id_photo_path', 'id_photo_thumb_path'])]
#[Hidden(['dni', 'dni_hash', 'id_photo_path', 'id_photo_thumb_path'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasDni, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dni' => 'encrypted',
        ];
    }

    /** @return HasMany<AuthorizedPerson, $this> */
    public function authorizedPeople(): HasMany
    {
        return $this->hasMany(AuthorizedPerson::class)->orderBy('full_name');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
