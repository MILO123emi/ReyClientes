<?php

namespace Database\Factories;

use App\Enums\PhotoOwner;
use App\Models\Client;
use App\Services\IdPhotoService;
use Database\Factories\Concerns\FakesHonduranData;
use Database\Factories\Concerns\MakesPlaceholderIdPhotos;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    use FakesHonduranData, MakesPlaceholderIdPhotos;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();

        return [
            'full_name' => $this->fakeFullName(),
            'dni' => $this->fakeDni(),
            'phone' => $this->fakePhone(),
            'id_photo_path' => "id-photos/clients/{$uuid}.enc",
            'id_photo_thumb_path' => "id-photos/clients/{$uuid}_thumb.enc",
        ];
    }

    public function withIdPhoto(): static
    {
        return $this->state(fn (array $attributes): array => app(IdPhotoService::class)->store($this->placeholderIdPhoto(PhotoOwner::Client), PhotoOwner::Client));
    }

    public function withAvatarPhoto(): static
    {
        return $this->state(fn (array $attributes): array => app(IdPhotoService::class)->store($this->avatarIdPhoto(PhotoOwner::Client), PhotoOwner::Client));
    }
}
