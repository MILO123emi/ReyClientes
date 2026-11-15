<?php

use App\Actions\Clients\CreateClient;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use App\Services\IdPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests from the client detail to login', function () {
    $this->get(route('clients.show', Client::factory()->create()))->assertRedirect(route('login'));
});

it('shows a client with every authorized person to every role using only safe fields', function (string $role) {
    $user = $role === 'owner' ? User::factory()->owner()->create() : User::factory()->create();
    $client = Client::factory()->create();
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Zoe Prueba']);
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Ana Prueba']);
    AuthorizedPerson::factory()->create();

    $response = $this->actingAs($user)->get(route('clients.show', $client))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('clients/Show')
            ->where('client.data.id', $client->id)->where('client.data.full_name', $client->full_name)
            ->where('client.data.people_count', 2)->has('client.data.authorized_people', 2)
            ->where('client.data.authorized_people.0.full_name', 'Ana Prueba')
            ->where('client.data.authorized_people.1.full_name', 'Zoe Prueba')
            ->has('relationships', 19));

    expect(json_encode($response->inertiaProps()))
        ->not->toContain('dni_hash', 'search_name', 'id-photos/', 'created_by', 'updated_by');
    expect($response->inertiaProps('client.data'))->toHaveKeys([
        'id', 'full_name', 'dni', 'phone', 'photo', 'people_count', 'authorized_people',
    ])->toHaveCount(7);
})->with(['employee', 'owner']);

it('returns not found for a missing client and ignores non numeric ids', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/clientes/999999')->assertNotFound();
    $this->get('/clientes/abc')->assertNotFound();
});

it('sends the owner back to the list after deleting a client from its detail page', function () {
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();

    $this->actingAs($user)->from(route('clients.show', $client))
        ->delete(route('clients.destroy', $client), ['current_password' => 'password'])
        ->assertRedirect(route('clients.index'));
    expect(Client::query()->whereKey($client->id)->exists())->toBeFalse();
});

it('keeps the search page after deleting a client from the list', function () {
    $user = User::factory()->owner()->create();
    $client = Client::factory()->create();
    $from = route('clients.index', ['q' => 'ana']);

    $this->actingAs($user)->from($from)
        ->delete(route('clients.destroy', $client), ['current_password' => 'password'])
        ->assertRedirect($from);
});

it('stores a development avatar through the encrypted photo service', function (string $model) {
    Storage::fake('local');

    $record = $model::factory()->withAvatarPhoto()->create();

    $full = app(IdPhotoService::class)->read($record->id_photo_path);
    expect(array_slice(getimagesizefromstring($full), 0, 2))->toBe([960, 640]);
    expect(getimagesizefromstring($full)['mime'])->toBe('image/jpeg');
    expect(Storage::disk('local')->get($record->id_photo_path))->not->toBe($full);
})->with(['client' => [Client::class], 'person' => [AuthorizedPerson::class]]);

it('ships ten development avatars outside the public directory', function () {
    $avatars = glob(database_path('seeders/assets/avatars/avatar-*.png'));

    expect($avatars)->toHaveCount(10);
    foreach ($avatars as $avatar) {
        expect(array_slice(getimagesize($avatar), 0, 2))->toBe([960, 640]);
    }
    expect(glob(public_path('**/avatar-*.png')))->toBe([]);
});

it('answers a duplicate identity that slips past validation with a form error instead of a server error', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->owner()->create());
    $existing = Client::factory()->create(['dni' => '0801199012345']);
    $photo = UploadedFile::fake()->image('cedula-ficticia.jpg', 500, 315);

    expect(fn () => app(CreateClient::class)->handle(
        ['full_name' => 'Otra Persona', 'dni' => $existing->dni, 'phone' => '99999999'], $photo,
    ))->toThrow(ValidationException::class, 'Ya existe un cliente con esta identidad.');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects a malformed login without a server error', function () {
    $this->post(route('login.store'), ['email' => ['a', 'b'], 'password' => 'x'])->assertSessionHasErrors('email');
});

it('makes sqlite wait for locks and write ahead', function () {
    expect(config('database.connections.sqlite'))->toMatchArray([
        'busy_timeout' => 5000, 'journal_mode' => 'wal', 'synchronous' => 'normal', 'transaction_mode' => 'IMMEDIATE',
    ]);
});
