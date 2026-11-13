<?php

use App\Actions\Clients\UpdateClient;
use App\Actions\People\CreateAuthorizedPerson;
use App\Actions\People\UpdateAuthorizedPerson;
use App\Enums\Relationship;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('rejects creating an authorized person when the parent identity changed after validation', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->owner()->create());
    $client = Client::factory()->withIdPhoto()->create(['dni' => '0801199012345']);
    $staleClient = Client::query()->findOrFail($client->id);
    $data = ['full_name' => 'Persona de Prueba', 'dni' => '0101198554321', 'phone' => '99999999', 'relationship' => Relationship::Son];
    $client->update(['dni' => $data['dni']]);
    $files = Storage::disk('local')->allFiles();

    expect(fn () => app(CreateAuthorizedPerson::class)->handle($staleClient, $data, UploadedFile::fake()->image('persona.jpg')))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toBe(['dni' => ['La persona autorizada no puede tener la misma identidad que el titular.']]);
        });

    $this->assertDatabaseCount('authorized_people', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe($files);
});

it('rejects updating an authorized person when the parent now has the submitted identity', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->owner()->create());
    $client = Client::factory()->withIdPhoto()->create(['dni' => '0801199012345']);
    $person = AuthorizedPerson::factory()->withIdPhoto()->for($client)->create(['dni' => '0301197056789']);
    $stalePerson = AuthorizedPerson::query()->findOrFail($person->id);
    $data = ['dni' => '0101198554321'];
    $client->update(['dni' => $data['dni']]);
    $files = Storage::disk('local')->allFiles();

    expect(fn () => app(UpdateAuthorizedPerson::class)->handle($stalePerson, $data, UploadedFile::fake()->image('nueva.jpg')))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toBe(['dni' => ['La persona autorizada no puede tener la misma identidad que el titular.']]);
        });

    expect($person->refresh()->dni)->toBe('0301197056789');
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe($files);
});

it('rejects updating a client identity after a person with that identity was added', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->owner()->create());
    $client = Client::factory()->withIdPhoto()->create(['dni' => '0801199012345']);
    $staleClient = Client::query()->findOrFail($client->id);
    $data = ['dni' => '0101198554321'];
    AuthorizedPerson::factory()->withIdPhoto()->for($client)->create(['dni' => $data['dni']]);
    $files = Storage::disk('local')->allFiles();

    expect(fn () => app(UpdateClient::class)->handle($staleClient, $data, UploadedFile::fake()->image('nueva.jpg')))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toBe(['dni' => ['Una persona autorizada de este cliente ya tiene esta identidad.']]);
        });

    expect($client->refresh()->dni)->toBe('0801199012345');
    $this->assertDatabaseCount('authorized_people', 1);
    $this->assertDatabaseCount('audit_logs', 0);
    expect(Storage::disk('local')->allFiles())->toBe($files);
});
