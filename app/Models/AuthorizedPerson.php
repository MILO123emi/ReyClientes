<?php

namespace App\Models;

use App\Enums\Relationship;
use App\Models\Concerns\HasDni;
use App\Models\Concerns\HasSearchName;
use Database\Factories\AuthorizedPersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $search_name
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
 * @property int $client_id
 * @property Relationship $relationship
 * @property string|null $relationship_other
 * @property-read Client $client
 */
#[Fillable(['full_name', 'dni', 'phone', 'id_photo_path', 'id_photo_thumb_path', 'relationship', 'relationship_other'])]
#[Hidden(['search_name', 'dni', 'dni_hash', 'id_photo_path', 'id_photo_thumb_path'])]
class AuthorizedPerson extends Model
{
    /** @use HasFactory<AuthorizedPersonFactory> */
    use HasDni, HasFactory, HasSearchName;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dni' => 'encrypted',
            'relationship' => Relationship::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $person): void {
            if ($person->relationship !== Relationship::Other) {
                $person->relationship_other = null;
            }
        });
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function relationshipLabel(): string
    {
        return $this->relationship === Relationship::Other
            ? (string) $this->relationship_other
            : $this->relationship->label();
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
