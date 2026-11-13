<?php

use App\Models\AuditLog;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** @return array<string, array<int, object>> */
function mutationDatabaseState(): array
{
    $state = [];
    foreach (['users', 'clients', 'authorized_people', 'sessions'] as $table) {
        $state[$table] = DB::table($table)->orderBy('id')->get()->all();
    }

    return $state;
}

/** @return array{0: string, 1: string, 2: array<string, mixed>, 3: string} */
function mutationEndpoint(string $operation, Client $client, AuthorizedPerson $person, User $target): array
{
    $clientData = ['full_name' => 'Ana De Prueba', 'dni' => '0101198554321', 'phone' => '99999999'];
    $personData = ['full_name' => 'Rosa De Prueba', 'dni' => '0202199656789', 'phone' => '89999999', 'relationship' => 'tia'];
    $photo = UploadedFile::fake()->image('cedula-ficticia.jpg', 500, 315);

    return match ($operation) {
        'client-create' => ['POST', route('clients.store'), [...$clientData, 'id_photo' => $photo], 'client.created'],
        'client-update' => ['PUT', route('clients.update', $client), $clientData, 'client.updated'],
        'client-delete' => ['DELETE', route('clients.destroy', $client), [], 'client.deleted'],
        'person-create' => ['POST', route('people.store', $client), [...$personData, 'id_photo' => $photo], 'person.created'],
        'person-update' => ['PUT', route('people.update', $person), $personData, 'person.updated'],
        'person-delete' => ['DELETE', route('people.destroy', $person), [], 'person.deleted'],
        'user-create' => ['POST', route('users.store'), [
            'name' => 'Usuario De Prueba', 'email' => 'newuser@example.com', 'password' => 'temporary-password',
            'password_confirmation' => 'temporary-password', 'can_manage_cards' => false,
        ], 'user.created'],
        'user-permission' => ['PUT', route('users.permission', $target), ['can_manage_cards' => true], 'user.permission_changed'],
        'user-password' => ['PUT', route('users.password', $target), [
            'password' => 'temporary-password', 'password_confirmation' => 'temporary-password',
        ], 'user.password_set_by_owner'],
        'user-delete' => ['DELETE', route('users.destroy', $target), [], 'user.deleted'],
    };
}

dataset('all protected mutations', [
    'client-create', 'client-update', 'client-delete', 'person-create', 'person-update', 'person-delete',
    'user-create', 'user-permission', 'user-password', 'user-delete',
]);

it('requires confirmation on every mutation and audits successful changes or incorrect confirmations', function (string $operation, string $passwordState) {
    Storage::fake('local');
    $owner = User::factory()->owner()->create();
    $target = User::factory()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $person = AuthorizedPerson::factory()->for($client)->withIdPhoto()->create();
    [$method, $uri, $payload, $action] = mutationEndpoint($operation, $client, $person, $target);
    if ($passwordState !== 'missing') {
        $payload['current_password'] = $passwordState === 'correct' ? 'password' : 'incorrect-secret';
    }
    $before = mutationDatabaseState();
    $files = Storage::disk('local')->allFiles();

    $response = $this->actingAs($owner)->call($method, $uri, $payload);

    if ($passwordState === 'correct') {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(mutationDatabaseState())->not->toEqual($before);
        expect(AuditLog::query()->sole()->action)->toBe($action);
    } else {
        $response->assertSessionHasErrors('current_password');
        expect(mutationDatabaseState())->toEqual($before);
        expect(Storage::disk('local')->allFiles())->toBe($files);
        $this->assertDatabaseCount('audit_logs', $passwordState === 'incorrect' ? 1 : 0);
        if ($passwordState === 'incorrect') {
            expect(AuditLog::query()->sole()->action)->toBe('auth.confirm_failed');
            expect(AuditLog::query()->sole()->description)->not->toContain('incorrect-secret');
        }
    }
})->with('all protected mutations')->with(['missing', 'incorrect', 'correct']);

it('enforces the full role matrix for every protected mutation', function (string $operation, string $role) {
    Storage::fake('local');
    $actor = match ($role) {
        'owner' => User::factory()->owner()->create(),
        'manager' => User::factory()->manager()->create(),
        default => User::factory()->create(),
    };
    $target = User::factory()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $person = AuthorizedPerson::factory()->for($client)->withIdPhoto()->create();
    [$method, $uri, $payload, $action] = mutationEndpoint($operation, $client, $person, $target);
    $payload['current_password'] = 'password';
    $before = mutationDatabaseState();
    $files = Storage::disk('local')->allFiles();
    $allowed = str_starts_with($operation, 'user-') ? $role === 'owner' : $role !== 'reader';

    $response = $this->actingAs($actor)->call($method, $uri, $payload);

    if ($allowed) {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(mutationDatabaseState())->not->toEqual($before);
        expect(AuditLog::query()->sole()->action)->toBe($action);
    } else {
        $response->assertForbidden();
        expect(mutationDatabaseState())->toEqual($before);
        expect(Storage::disk('local')->allFiles())->toBe($files);
        $this->assertDatabaseCount('audit_logs', 0);
    }
})->with('all protected mutations')->with(['reader', 'manager', 'owner']);

it('requires authentication at every mutation endpoint', function (string $operation) {
    Storage::fake('local');
    $target = User::factory()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $person = AuthorizedPerson::factory()->for($client)->withIdPhoto()->create();
    [$method, $uri, $payload] = mutationEndpoint($operation, $client, $person, $target);
    $payload['current_password'] = 'password';
    $before = mutationDatabaseState();

    $this->call($method, $uri, $payload)->assertRedirect(route('login'));

    expect(mutationDatabaseState())->toEqual($before);
    $this->assertDatabaseCount('audit_logs', 0);
})->with('all protected mutations');
