<?php

use App\Actions\Audit\RecordAudit;
use App\Enums\Relationship;
use App\Models\AuditLog;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function personEndpointPayload(array $overrides = []): array
{
    return array_replace([
        'full_name' => '  rosa   de prueba  ',
        'dni' => '0101-1985-54321',
        'phone' => '+504 8999-9999',
        'relationship' => 'tio',
        'relationship_other' => null,
        'id_photo' => UploadedFile::fake()->image('cedula-ficticia.jpg', 500, 315),
        'current_password' => 'password',
    ], $overrides);
}

it('creates an authorized person with normalized encrypted data photos attribution and masked audit', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $other = Client::factory()->create();

    $this->actingAs($user)->from(route('clients.index'))->post(route('people.store', $client), personEndpointPayload([
        'client_id' => $other->id, 'created_by' => 999, 'updated_by' => 999, 'dni_hash' => 'forged',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('clients.index'));

    $person = AuthorizedPerson::query()->sole();
    expect($person->client_id)->toBe($client->id);
    expect($person->full_name)->toBe('Rosa De Prueba');
    expect($person->dni)->toBe('0101198554321');
    expect($person->phone)->toBe('89999999');
    expect($person->relationship)->toBe(Relationship::Uncle);
    expect($person->relationshipLabel())->toBe('Tío');
    expect($person->created_by)->toBe($user->id);
    expect($person->updated_by)->toBe($user->id);
    expect(DB::table('authorized_people')->value('dni'))->not->toBe('0101198554321')->not->toContain('0101198554321');
    Storage::disk('local')->assertExists([$person->id_photo_path, $person->id_photo_thumb_path]);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('person.created');
    expect($log->subject_type)->toBe('person');
    expect($log->subject_id)->toBe($person->id);
    expect($log->user_id)->toBe($user->id);
    expect($log->description)->toContain('Persona Rosa De Prueba (****-****-*4321)', 'del cliente '.$client->full_name)
        ->not->toContain('0101198554321', '0101-1985-54321', 'password');
});

it('rejects an identity equal to the titulars identity on create and update', function (string $operation) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create(['dni' => '0101198554321']);
    $person = AuthorizedPerson::factory()->for($client)->create();
    $original = $person->dni;

    $response = $this->actingAs($user)->call($operation === 'create' ? 'POST' : 'PUT',
        $operation === 'create' ? route('people.store', $client) : route('people.update', $person),
        personEndpointPayload(['id_photo' => $operation === 'create' ? UploadedFile::fake()->image('prueba.jpg') : null]));

    $response->assertSessionHasErrors(['dni' => 'La persona autorizada no puede tener la misma identidad que el titular.']);
    $this->assertDatabaseCount('authorized_people', 1);
    expect($person->refresh()->dni)->toBe($original);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['create', 'update']);

it('rejects a duplicate authorized identity in the same client on create and update', function (string $operation) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    AuthorizedPerson::factory()->for($client)->create(['dni' => '0101198554321']);
    $person = AuthorizedPerson::factory()->for($client)->create();
    $original = $person->dni;

    $response = $this->actingAs($user)->call($operation === 'create' ? 'POST' : 'PUT',
        $operation === 'create' ? route('people.store', $client) : route('people.update', $person),
        personEndpointPayload(['id_photo' => $operation === 'create' ? UploadedFile::fake()->image('prueba.jpg') : null]));

    $response->assertSessionHasErrors(['dni' => 'Esta persona ya está autorizada por este cliente.']);
    $this->assertDatabaseCount('authorized_people', 2);
    expect($person->refresh()->dni)->toBe($original);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['create', 'update']);

it('allows the same authorized identity in two different clients', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $clients = Client::factory()->count(2)->create();
    $this->actingAs($user);

    foreach ($clients as $client) {
        $this->post(route('people.store', $client), personEndpointPayload())->assertSessionHasNoErrors();
    }

    $this->assertDatabaseCount('authorized_people', 2);
    expect(AuthorizedPerson::query()->pluck('dni_hash')->unique())->toHaveCount(1);
    expect(AuthorizedPerson::query()->pluck('client_id')->unique())->toHaveCount(2);
    expect(Storage::disk('local')->allFiles())->toHaveCount(4);
});

it('rejects missing or invalid relationships and unspecified other relationships', function (string $kind, string $field) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $overrides = match ($kind) {
        'missing' => ['relationship' => null],
        'invalid' => ['relationship' => 'desconocido'],
        'other-empty' => ['relationship' => 'otro'],
        'other-whitespace' => ['relationship' => 'otro', 'relationship_other' => '   '],
        'other-too-long' => ['relationship' => 'otro', 'relationship_other' => str_repeat('a', 51)],
    };

    $response = $this->actingAs($user)->post(route('people.store', $client), personEndpointPayload($overrides));

    $response->assertSessionHasErrors($field);
    if (in_array($kind, ['other-empty', 'other-whitespace'])) {
        $response->assertSessionHasErrors(['relationship_other' => 'Especifica el parentesco.']);
    }
    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'required relationship' => ['missing', 'relationship'],
    'invalid relationship' => ['invalid', 'relationship'],
    'other without specification' => ['other-empty', 'relationship_other'],
    'other with blank specification' => ['other-whitespace', 'relationship_other'],
    'other specification over fifty characters' => ['other-too-long', 'relationship_other'],
]);

it('exposes the trimmed custom relationship label and clears it when another relationship is chosen', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $this->actingAs($user)->post(route('people.store', $client), personEndpointPayload([
        'relationship' => 'otro', 'relationship_other' => '  Vecino  ',
    ]))->assertSessionHasNoErrors();
    $person = AuthorizedPerson::query()->sole();

    $this->get(route('clients.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('clients.data.0.authorized_people.0.relationship_label', 'Vecino')
            ->where('clients.data.0.authorized_people.0.relationship_other', 'Vecino'));
    $this->put(route('people.update', $person), personEndpointPayload([
        'relationship' => 'madre', 'relationship_other' => 'Vecino', 'id_photo' => null,
    ]))->assertSessionHasNoErrors();

    expect($person->refresh()->relationship)->toBe(Relationship::Mother);
    expect($person->relationshipLabel())->toBe('Madre');
    expect($person->relationship_other)->toBeNull();
});

it('keeps the persons own identity and photos when editing other fields', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $person = AuthorizedPerson::factory()->withIdPhoto()->create(['dni' => '0101198554321']);
    $paths = [$person->id_photo_path, $person->id_photo_thumb_path];

    $this->actingAs($user)->put(route('people.update', $person), personEndpointPayload(['id_photo' => null]))
        ->assertSessionHasNoErrors();

    expect($person->refresh()->full_name)->toBe('Rosa De Prueba');
    expect($person->dni)->toBe('0101198554321');
    expect([$person->id_photo_path, $person->id_photo_thumb_path])->toBe($paths);
    Storage::disk('local')->assertExists($paths);
    expect(AuditLog::query()->sole()->action)->toBe('person.updated');
});

it('replaces authorized person photos and removes the old files after commit', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $person = AuthorizedPerson::factory()->withIdPhoto()->create();
    $oldPaths = [$person->id_photo_path, $person->id_photo_thumb_path];

    $this->actingAs($user)->put(route('people.update', $person), personEndpointPayload())->assertSessionHasNoErrors();

    $person->refresh();
    $newPaths = [$person->id_photo_path, $person->id_photo_thumb_path];
    expect($newPaths)->not->toBe($oldPaths);
    Storage::disk('local')->assertExists($newPaths);
    Storage::disk('local')->assertMissing($oldPaths);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    expect(AuditLog::query()->sole()->description)->toContain('Persona Rosa De Prueba (****-****-*4321)', 'del cliente '.$person->client->full_name);
});

it('preserves existing person data and files when audit fails during replacement', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $person = AuthorizedPerson::factory()->withIdPhoto()->create();
    $original = DB::table('authorized_people')->where('id', $person->id)->first();
    $files = Storage::disk('local')->allFiles();
    $this->mock(RecordAudit::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Fallo de auditoría de prueba.'));
    Exceptions::fake();

    $this->actingAs($user)->put(route('people.update', $person), personEndpointPayload())->assertInternalServerError();

    expect(DB::table('authorized_people')->where('id', $person->id)->first())->toEqual($original);
    expect(Storage::disk('local')->allFiles())->toBe($files);
    $this->assertDatabaseCount('audit_logs', 0);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Fallo de auditoría de prueba.');
});

it('cleans up newly stored photos if authorized person creation rolls back', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $this->mock(RecordAudit::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Fallo de auditoría de prueba.'));
    Exceptions::fake();

    $this->actingAs($user)->post(route('people.store', $client), personEndpointPayload())->assertInternalServerError();

    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Fallo de auditoría de prueba.');
});

it('deletes only the selected persons rows and photos and retains the client and other people', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $person = AuthorizedPerson::factory()->for($client)->withIdPhoto()->create(['full_name' => 'Rosa De Prueba', 'dni' => '0101198554321']);
    $other = AuthorizedPerson::factory()->for($client)->withIdPhoto()->create();
    $removedPaths = [$person->id_photo_path, $person->id_photo_thumb_path];
    $retainedPaths = [$client->id_photo_path, $client->id_photo_thumb_path, $other->id_photo_path, $other->id_photo_thumb_path];

    $this->actingAs($user)->delete(route('people.destroy', $person), ['current_password' => 'password'])->assertSessionHasNoErrors();

    $this->assertModelMissing($person);
    $this->assertModelExists($client);
    $this->assertModelExists($other);
    Storage::disk('local')->assertMissing($removedPaths);
    Storage::disk('local')->assertExists($retainedPaths);
    expect(Storage::disk('local')->allFiles())->toHaveCount(4);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('person.deleted');
    expect($log->description)->toContain('Persona Rosa De Prueba (****-****-*4321)', 'del cliente '.$client->full_name)
        ->not->toContain('0101198554321', '0101-1985-54321');
});

it('returns 404 for nonexistent authorized people', function (string $method) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->call($method, '/autorizados/999999', personEndpointPayload())->assertNotFound();

    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['PUT', 'DELETE']);

it('enforces authorized person mutation permissions for every role and endpoint', function (string $role, string $operation) {
    Storage::fake('local');
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        default => User::factory()->create(),
    };
    $person = AuthorizedPerson::factory()->withIdPhoto()->create();
    $before = DB::table('authorized_people')->get()->toArray();
    $files = Storage::disk('local')->allFiles();
    [$method, $uri] = match ($operation) {
        'create' => ['POST', route('people.store', $person->client)],
        'update' => ['PUT', route('people.update', $person)],
        'delete' => ['DELETE', route('people.destroy', $person)],
    };
    $payload = $operation === 'delete' ? ['current_password' => 'password'] : personEndpointPayload();

    $response = $this->actingAs($user)->call($method, $uri, $payload);

    if ($role === 'employee') {
        $response->assertForbidden();
        expect(DB::table('authorized_people')->get()->toArray())->toEqual($before);
        expect(Storage::disk('local')->allFiles())->toBe($files);
        $this->assertDatabaseCount('audit_logs', 0);
    } else {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(AuditLog::query()->sole()->action)->toBe('person.'.match ($operation) {
            'create' => 'created', 'update' => 'updated', 'delete' => 'deleted',
        });
    }
})->with(['employee', 'owner'])->with(['create', 'update', 'delete']);

it('rejects missing and incorrect passwords without changing authorized people or photos', function (string $operation, string $passwordState) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $person = AuthorizedPerson::factory()->withIdPhoto()->create();
    $before = DB::table('authorized_people')->get()->toArray();
    $files = Storage::disk('local')->allFiles();
    [$method, $uri] = match ($operation) {
        'create' => ['POST', route('people.store', $person->client)],
        'update' => ['PUT', route('people.update', $person)],
        'delete' => ['DELETE', route('people.destroy', $person)],
    };
    $payload = $operation === 'delete' ? [] : personEndpointPayload();
    unset($payload['current_password']);
    if ($passwordState === 'incorrect') {
        $payload['current_password'] = 'incorrect-secret';
    }

    $this->actingAs($user)->call($method, $uri, $payload)->assertSessionHasErrors('current_password');

    expect(DB::table('authorized_people')->get()->toArray())->toEqual($before);
    expect(Storage::disk('local')->allFiles())->toBe($files);
    $this->assertDatabaseCount('audit_logs', $passwordState === 'incorrect' ? 1 : 0);
    if ($passwordState === 'incorrect') {
        expect(AuditLog::query()->sole()->action)->toBe('auth.confirm_failed');
    }
})->with(['create', 'update', 'delete'])->with(['missing', 'incorrect']);
