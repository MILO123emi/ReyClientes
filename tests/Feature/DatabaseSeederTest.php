<?php

use App\Models\AuditLog;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use App\Support\Dni;

it('seeds fifteen local clients and up to four authorized people without duplication', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $this->seed();

    $owner = User::query()->where('is_owner', true)->sole();
    $clients = Client::withCount('authorizedPeople')->get();
    $this->assertDatabaseCount('clients', 15);
    foreach ($clients as $client) {
        expect($client->authorized_people_count)->toBeBetween(0, 4);
        expect($client->created_by)->toBe($owner->id);
        expect($client->dni_hash)->toBe(Dni::hash($client->dni));
    }
    foreach (AuthorizedPerson::all() as $person) {
        expect($person->created_by)->toBe($owner->id);
        expect($person->dni_hash)->toBe(Dni::hash($person->dni));
    }
    $peopleCount = AuthorizedPerson::query()->count();
    $auditCount = AuditLog::query()->count();
    $clientIds = $clients->modelKeys();

    $this->seed();

    $this->assertDatabaseCount('clients', 15);
    $this->assertDatabaseCount('authorized_people', $peopleCount);
    $this->assertDatabaseCount('audit_logs', $auditCount);
    expect(Client::all()->modelKeys())->toBe($clientIds);
});

it('does not seed clients people users or audits outside local', function (string $environment) {
    $this->app->detectEnvironment(fn () => $environment);

    $this->seed();

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('audit_logs', 0);
})->with(['testing', 'staging', 'production']);
