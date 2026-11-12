<?php

use App\Enums\Relationship;
use App\Models\AuthorizedPerson;
use App\Models\Client;

it('generates fictional identities phones names and UUID photo paths', function (string $model) {
    $records = $model::factory()->count(20)->create();

    foreach ($records as $record) {
        expect($record->dni)->toMatch('/^\d{13}$/');
        expect((int) substr($record->dni, 0, 2))->toBeBetween(1, 18);
        expect((int) substr($record->dni, 2, 2))->toBeBetween(1, 20);
        expect((int) substr($record->dni, 4, 4))->toBeBetween(1950, 2008);
        expect($record->phone)->toMatch('/^[389]\d{7}$/');
        expect(explode(' ', $record->full_name))->toHaveCount(3);
        expect($record->id_photo_path)->toMatch('~^id-photos/(clients|people)/[a-f0-9-]{36}\.jpg$~');
        expect($record->id_photo_thumb_path)->toBe(substr($record->id_photo_path, 0, -4).'_thumb.jpg');
    }
})->with(['client' => [Client::class], 'person' => [AuthorizedPerson::class]]);

it('generates a free text relationship with the other relationship state', function () {
    $person = AuthorizedPerson::factory()->otherRelationship()->create();

    expect($person->relationship)->toBe(Relationship::Other);
    expect($person->relationship_other)->toBeIn(['Cuñada', 'Vecino', 'Suegra', 'Ahijado']);
});
