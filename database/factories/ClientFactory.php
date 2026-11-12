<?php

namespace Database\Factories;

use App\Models\Client;
use Database\Factories\Concerns\FakesHonduranData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    use FakesHonduranData;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();

        return [
            'full_name' => $this->fakeFullName(),
            'dni' => $this->fakeDni(),
            'phone' => $this->fakePhone(),
            'id_photo_path' => "id-photos/clients/{$uuid}.jpg",
            'id_photo_thumb_path' => "id-photos/clients/{$uuid}_thumb.jpg",
        ];
    }
}
