<?php

namespace Database\Factories;

use App\Enums\Relationship;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use Database\Factories\Concerns\FakesHonduranData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuthorizedPerson> */
class AuthorizedPersonFactory extends Factory
{
    use FakesHonduranData;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uuid = Str::uuid()->toString();
        $relationship = fake()->randomElement(Relationship::cases());

        return [
            'client_id' => Client::factory(),
            'full_name' => $this->fakeFullName(),
            'dni' => $this->fakeDni(),
            'phone' => $this->fakePhone(),
            'id_photo_path' => "id-photos/people/{$uuid}.jpg",
            'id_photo_thumb_path' => "id-photos/people/{$uuid}_thumb.jpg",
            'relationship' => $relationship,
            'relationship_other' => $relationship === Relationship::Other ? fake()->randomElement(['Cuñada', 'Vecino', 'Suegra', 'Ahijado']) : null,
        ];
    }

    public function otherRelationship(): static
    {
        return $this->state(fn (array $attributes): array => [
            'relationship' => Relationship::Other,
            'relationship_other' => fake()->randomElement(['Cuñada', 'Vecino', 'Suegra', 'Ahijado']),
        ]);
    }
}
