<?php

use App\Models\AuditLog;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use App\Services\IdPhotoService;
use App\Support\Dni;
use Illuminate\Support\Facades\Storage;

it('seeds fifteen local clients and up to four authorized people without duplication', function () {
    Storage::fake('local');
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
    $files = Storage::disk('local')->allFiles('id-photos');
    expect($files)->toHaveCount((15 + $peopleCount) * 2);
    foreach ($clients->concat(AuthorizedPerson::all()) as $record) {
        foreach ([$record->id_photo_path, $record->id_photo_thumb_path] as $path) {
            Storage::disk('local')->assertExists($path);
            expect(getimagesizefromstring(app(IdPhotoService::class)->read($path))['mime'])->toBe('image/jpeg');
        }
    }

    $this->seed();

    $this->assertDatabaseCount('clients', 15);
    $this->assertDatabaseCount('authorized_people', $peopleCount);
    $this->assertDatabaseCount('audit_logs', $auditCount);
    expect(Client::all()->modelKeys())->toBe($clientIds);
    expect(Storage::disk('local')->allFiles('id-photos'))->toBe($files);
});

it('does not seed clients people users or audits outside local', function (string $environment) {
    Storage::fake('local');
    $this->app->detectEnvironment(fn () => $environment);

    $this->seed();

    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    Storage::disk('local')->assertDirectoryEmpty('/');
})->with(['testing', 'staging', 'production']);
