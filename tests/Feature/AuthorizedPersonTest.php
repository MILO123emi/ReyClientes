<?php

use App\Enums\Relationship;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use App\Support\Dni;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('allows the same identity under two clients', function () {
    $people = AuthorizedPerson::factory()->count(2)->create(['dni' => '0801199012345']);

    expect($people->pluck('client_id')->unique())->toHaveCount(2);
    $this->assertDatabaseCount('authorized_people', 2);
});

it('rejects a duplicate identity under the same client', function () {
    $person = AuthorizedPerson::factory()->create(['dni' => '0801199012345']);

    expect(fn () => AuthorizedPerson::factory()->for($person->client)->create(['dni' => '0801-1990-12345']))
        ->toThrow(QueryException::class);
    $this->assertDatabaseCount('authorized_people', 1);
});

it('encrypts identities and updates their hash through the shared concern', function () {
    $person = AuthorizedPerson::factory()->create(['dni' => '0801-1990-12345']);

    expect(DB::table('authorized_people')->value('dni'))->not->toBe('0801199012345')->not->toContain('0801199012345');
    expect($person->refresh()->dni)->toBe('0801199012345');
    expect($person->dni_hash)->toBe(Dni::hash('0801199012345'));
    expect($person->maskedDni())->toBe('****-****-*2345');
    expect(AuthorizedPerson::query()->byDni('0801-1990-12345')->sole()->is($person))->toBeTrue();

    $person->update(['dni' => '0101-1985-54321']);

    expect($person->refresh()->dni_hash)->toBe(Dni::hash('0101198554321'));
    expect(AuthorizedPerson::query()->byDni('0801199012345')->exists())->toBeFalse();
});

it('casts the relationship and labels accented values', function () {
    $person = AuthorizedPerson::factory()->create(['relationship' => 'tio', 'relationship_other' => 'Texto sobrante']);

    expect($person->refresh()->relationship)->toBe(Relationship::Uncle);
    expect($person->relationshipLabel())->toBe('Tío');
    expect($person->relationship_other)->toBeNull();
});

it('uses the custom relationship and clears it when another relationship is chosen', function () {
    $person = AuthorizedPerson::factory()->otherRelationship()->create(['relationship_other' => 'Vecino']);
    expect($person->refresh()->relationship)->toBe(Relationship::Other);
    expect($person->relationshipLabel())->toBe('Vecino');

    $person->update(['relationship' => Relationship::Aunt]);

    expect($person->refresh()->relationshipLabel())->toBe('Tía');
    $this->assertDatabaseHas('authorized_people', ['id' => $person->id, 'relationship_other' => null]);
});

it('orders authorized people by name and deletes them with their client', function () {
    $client = Client::factory()->create();
    $people = AuthorizedPerson::factory()->count(3)->for($client)->sequence(
        ['full_name' => 'Zulema de Prueba'], ['full_name' => 'Ana de Prueba'], ['full_name' => 'Mario de Prueba'],
    )->create();

    expect($client->authorizedPeople->pluck('full_name')->all())->toBe(['Ana de Prueba', 'Mario de Prueba', 'Zulema de Prueba']);
    expect($people->first()->client->is($client))->toBeTrue();
    $client->delete();

    $this->assertDatabaseCount('authorized_people', 0);
});

it('hides identity and photo paths from serialization', function () {
    $person = AuthorizedPerson::factory()->create();

    expect($person->refresh()->toArray())->toHaveKeys(['id', 'client_id', 'full_name', 'phone', 'relationship', 'relationship_other', 'created_by', 'updated_by', 'created_at', 'updated_at'])
        ->toHaveCount(10);
});

it('clears attribution while retaining authorized people when the author is deleted', function () {
    $user = User::factory()->create();
    $person = AuthorizedPerson::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
    expect($person->creator->is($user))->toBeTrue();
    expect($person->updater->is($user))->toBeTrue();

    $user->delete();

    $this->assertModelExists($person);
    expect($person->refresh()->created_by)->toBeNull();
    expect($person->updated_by)->toBeNull();
});
