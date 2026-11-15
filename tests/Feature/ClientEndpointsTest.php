<?php

use App\Actions\Audit\RecordAudit;
use App\Models\AuditLog;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function clientEndpointPayload(array $overrides = []): array
{
    return array_replace([
        'full_name' => '  ana   de prueba  ',
        'dni' => '0801-1990-12345',
        'phone' => '+504 9999-9999',
        'id_photo' => UploadedFile::fake()->image('cedula-ficticia.jpg', 500, 315),
        'current_password' => 'password',
    ], $overrides);
}

it('redirects guests from the clients index to login', function () {
    $this->get(route('clients.index'))->assertRedirect(route('login'));
});

it('lists twenty clients per page for every role with only safe resource fields', function (string $role) {
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        default => User::factory()->create(),
    };
    Client::factory()->count(21)->create();
    $first = Client::query()->orderBy('full_name')->first();
    AuthorizedPerson::factory()->for($first)->create();

    $response = $this->actingAs($user)->get(route('clients.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clients/Index')
            ->has('clients.data', 20)->where('clients.meta.per_page', 20)->where('clients.meta.last_page', 2)
            ->where('clients.data.0.id', $first->id)->where('clients.data.0.people_count', 1)
            ->has('clients.data.0.authorized_people', 1)->has('relationships', 19));

    $props = json_encode($response->inertiaProps());
    expect($props)->not->toContain('dni_hash', 'id-photos/', 'created_by', 'updated_by');
    expect($response->inertiaProps('clients.data.0'))->toHaveKeys([
        'id', 'full_name', 'dni', 'phone', 'photo', 'people_count', 'authorized_people',
    ])->toHaveCount(7);
    expect($response->inertiaProps('clients.data.0.authorized_people.0'))->toHaveKeys([
        'id', 'client_id', 'full_name', 'dni', 'phone', 'relationship', 'relationship_label', 'relationship_other', 'photo',
    ])->toHaveCount(9);
    $this->get(route('clients.index', ['page' => 2]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.meta.current_page', 2));
})->with(['employee', 'owner']);

it('creates normalized clients with encrypted identity photos attribution and masked audit', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();

    $this->actingAs($user)->from(route('clients.index'))->post(route('clients.store'), clientEndpointPayload([
        'created_by' => 999, 'updated_by' => 999, 'dni_hash' => 'forged',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('clients.index'));

    $client = Client::query()->sole();
    expect($client->full_name)->toBe('Ana De Prueba');
    expect($client->dni)->toBe('0801199012345');
    expect($client->phone)->toBe('99999999');
    expect($client->created_by)->toBe($user->id);
    expect($client->updated_by)->toBe($user->id);
    expect(DB::table('clients')->value('dni'))->not->toBe('0801199012345')->not->toContain('0801199012345');
    Storage::disk('local')->assertExists([$client->id_photo_path, $client->id_photo_thumb_path]);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('client.created');
    expect($log->subject_type)->toBe('client');
    expect($log->subject_id)->toBe($client->id);
    expect($log->user_id)->toBe($user->id);
    expect($log->description)->toBe('Cliente Ana De Prueba (****-****-*2345)')
        ->not->toContain('0801199012345', '0801-1990-12345', 'password');
});

it('requires every client field without writing files rows or audit', function (string $field) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $payload = clientEndpointPayload();
    unset($payload[$field]);

    $this->actingAs($user)->post(route('clients.store'), $payload)->assertSessionHasErrors($field);

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['full_name', 'dni', 'phone', 'id_photo']);

it('rejects invalid client fields with the specified Spanish validation message', function (string $kind, string $field, string $message) {
    Storage::fake('local');
    $this->travelTo(now()->startOfDay());
    $user = User::factory()->owner()->create();
    $value = match ($kind) {
        'short-dni' => '080119901234',
        'zero-department' => '0001199012345',
        'high-department' => '1901199012345',
        'old-year' => '0801185012345',
        'future-year' => '0801'.(now()->year + 1).'12345',
        'short-phone' => '9999999',
        'low-phone' => '19999999',
        'invalid-name' => 'Ana 123',
        'pdf' => UploadedFile::fake()->create('prueba.pdf', 10, 'application/pdf'),
        'oversize' => UploadedFile::fake()->image('prueba.jpg')->size(8193),
    };

    $this->actingAs($user)->post(route('clients.store'), clientEndpointPayload([$field => $value]))
        ->assertSessionHasErrors([$field => $message]);

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'twelve digits' => ['short-dni', 'dni', 'La identidad debe tener 13 dígitos.'],
    'department 00' => ['zero-department', 'dni', 'El departamento de la identidad no es válido.'],
    'department 19' => ['high-department', 'dni', 'El departamento de la identidad no es válido.'],
    'year 1850' => ['old-year', 'dni', 'El año de la identidad no es válido.'],
    'future year' => ['future-year', 'dni', 'El año de la identidad no es válido.'],
    'seven phone digits' => ['short-phone', 'phone', 'El teléfono debe tener 8 dígitos y empezar con 2 a 9.'],
    'phone starts with 1' => ['low-phone', 'phone', 'El teléfono debe tener 8 dígitos y empezar con 2 a 9.'],
    'name with numbers' => ['invalid-name', 'full_name', 'El nombre solo puede tener letras, espacios, apóstrofos y guiones.'],
    'PDF photo' => ['pdf', 'id_photo', 'La foto de la cédula debe ser una imagen JPG, PNG, WebP, GIF, BMP o AVIF.'],
    'photo above 8 MB' => ['oversize', 'id_photo', 'El campo foto de la cédula no debe ser mayor que 8192 kilobytes.'],
]);

it('rejects duplicate client identities before writing any photos', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    Client::factory()->create(['dni' => '0801199012345']);

    $this->actingAs($user)->post(route('clients.store'), clientEndpointPayload())
        ->assertSessionHasErrors(['dni' => 'Ya existe un cliente con esta identidad.']);

    $this->assertDatabaseCount('clients', 1);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('allows editing with the clients own identity and preserves existing photos', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create(['dni' => '0801199012345']);
    $paths = [$client->id_photo_path, $client->id_photo_thumb_path];

    $this->actingAs($user)->put(route('clients.update', $client), clientEndpointPayload(['id_photo' => null]))
        ->assertSessionHasNoErrors();

    expect($client->refresh()->full_name)->toBe('Ana De Prueba');
    expect($client->dni)->toBe('0801199012345');
    expect([$client->id_photo_path, $client->id_photo_thumb_path])->toBe($paths);
    Storage::disk('local')->assertExists($paths);
    expect(AuditLog::query()->sole()->action)->toBe('client.updated');
});

it('rejects a client identity already assigned to one of its authorized people', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $original = $client->dni;
    AuthorizedPerson::factory()->for($client)->create(['dni' => '0801199012345']);

    $this->actingAs($user)->put(route('clients.update', $client), clientEndpointPayload(['id_photo' => null]))
        ->assertSessionHasErrors(['dni' => 'Una persona autorizada de este cliente ya tiene esta identidad.']);

    expect($client->refresh()->dni)->toBe($original);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('replaces client photos and deletes the old files after commit', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $oldPaths = [$client->id_photo_path, $client->id_photo_thumb_path];

    $this->actingAs($user)->put(route('clients.update', $client), clientEndpointPayload())->assertSessionHasNoErrors();

    $client->refresh();
    $newPaths = [$client->id_photo_path, $client->id_photo_thumb_path];
    expect($newPaths)->not->toBe($oldPaths);
    Storage::disk('local')->assertExists($newPaths);
    Storage::disk('local')->assertMissing($oldPaths);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    expect(AuditLog::query()->sole()->description)->toContain('editado:', 'foto');
});

it('does not write or audit an unchanged client', function () {
    Storage::fake('local');
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create(['full_name' => 'Ana De Prueba']);
    $updatedAt = $client->updated_at->toISOString();
    $this->travel(60)->seconds();

    $this->actingAs($user)->put(route('clients.update', $client), [
        'full_name' => $client->full_name, 'dni' => $client->dni, 'phone' => $client->phone, 'current_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($client->refresh()->updated_at->toISOString())->toBe($updatedAt);
    expect($client->updated_by)->toBeNull();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('restores old client data and photos when audit fails during replacement', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $original = DB::table('clients')->where('id', $client->id)->first();
    $files = Storage::disk('local')->allFiles();
    $this->mock(RecordAudit::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Fallo de auditoría de prueba.'));
    Exceptions::fake();

    $this->actingAs($user)->put(route('clients.update', $client), clientEndpointPayload())->assertInternalServerError();

    expect(DB::table('clients')->where('id', $client->id)->first())->toEqual($original);
    expect(Storage::disk('local')->allFiles())->toBe($files);
    $this->assertDatabaseCount('audit_logs', 0);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Fallo de auditoría de prueba.');
});

it('removes newly stored photos if client creation rolls back', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $this->mock(RecordAudit::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Fallo de auditoría de prueba.'));
    Exceptions::fake();

    $this->actingAs($user)->post(route('clients.store'), clientEndpointPayload())->assertInternalServerError();

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Fallo de auditoría de prueba.');
});

it('deletes clients their authorized people and all photos with a masked counted audit', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create(['full_name' => 'Ana De Prueba', 'dni' => '0801199012345']);
    AuthorizedPerson::factory()->count(2)->for($client)->withIdPhoto()->create();
    $other = Client::factory()->withIdPhoto()->create();
    $otherPaths = [$other->id_photo_path, $other->id_photo_thumb_path];

    $this->actingAs($user)->delete(route('clients.destroy', $client), ['current_password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($client);
    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertModelExists($other);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    Storage::disk('local')->assertExists($otherPaths);
    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('client.deleted');
    expect($log->description)->toBe('Cliente Ana De Prueba (****-****-*2345) eliminado con 2 personas autorizadas')
        ->not->toContain('0801199012345', '0801-1990-12345');
});

it('enforces client mutation permissions for every role and endpoint', function (string $role, string $operation) {
    Storage::fake('local');
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        default => User::factory()->create(),
    };
    $client = Client::factory()->withIdPhoto()->create();
    $before = DB::table('clients')->get()->toArray();
    $files = Storage::disk('local')->allFiles();
    [$method, $uri] = match ($operation) {
        'create' => ['POST', route('clients.store')],
        'update' => ['PUT', route('clients.update', $client)],
        'delete' => ['DELETE', route('clients.destroy', $client)],
    };
    $payload = $operation === 'delete' ? ['current_password' => 'password'] : clientEndpointPayload();

    $response = $this->actingAs($user)->call($method, $uri, $payload);

    if ($role === 'employee') {
        $response->assertForbidden();
        expect(DB::table('clients')->get()->toArray())->toEqual($before);
        expect(Storage::disk('local')->allFiles())->toBe($files);
        $this->assertDatabaseCount('audit_logs', 0);
    } else {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(AuditLog::query()->sole()->action)->toBe('client.'.match ($operation) {
            'create' => 'created', 'update' => 'updated', 'delete' => 'deleted',
        });
    }
})->with(['employee', 'owner'])->with(['create', 'update', 'delete']);

it('rejects missing and incorrect passwords without changing clients or photos', function (string $operation, string $passwordState) {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $before = DB::table('clients')->get()->toArray();
    $files = Storage::disk('local')->allFiles();
    [$method, $uri] = match ($operation) {
        'create' => ['POST', route('clients.store')],
        'update' => ['PUT', route('clients.update', $client)],
        'delete' => ['DELETE', route('clients.destroy', $client)],
    };
    $payload = $operation === 'delete' ? [] : clientEndpointPayload();
    unset($payload['current_password']);
    if ($passwordState === 'incorrect') {
        $payload['current_password'] = 'incorrect-secret';
    }

    $this->actingAs($user)->call($method, $uri, $payload)->assertSessionHasErrors('current_password');

    expect(DB::table('clients')->get()->toArray())->toEqual($before);
    expect(Storage::disk('local')->allFiles())->toBe($files);
    $this->assertDatabaseCount('audit_logs', $passwordState === 'incorrect' ? 1 : 0);
    if ($passwordState === 'incorrect') {
        expect(AuditLog::query()->sole()->action)->toBe('auth.confirm_failed');
    }
})->with(['create', 'update', 'delete'])->with(['missing', 'incorrect']);

it('revokes client mutation permission on the next request in the same session', function () {
    Storage::fake('local');
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    User::query()->whereKey($user->id)->update(['is_owner' => false]);
    $this->app['auth']->forgetGuards();

    $this->put(route('clients.update', $client), clientEndpointPayload(['id_photo' => null]))->assertForbidden();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
});
