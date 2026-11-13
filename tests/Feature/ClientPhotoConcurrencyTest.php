<?php

use App\Actions\Clients\DeleteClient;
use App\Actions\Clients\UpdateClient;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('removes the latest photo pair when replacing through a stale client model', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->manager()->create());
    $client = Client::factory()->withIdPhoto()->create();
    $staleClient = Client::query()->findOrFail($client->id);
    $originalPaths = [$client->id_photo_path, $client->id_photo_thumb_path];
    $first = app(UpdateClient::class)->handle($client, ['phone' => '99999999'], UploadedFile::fake()->image('primera.jpg'));
    $firstPaths = [$first->id_photo_path, $first->id_photo_thumb_path];

    $second = app(UpdateClient::class)->handle($staleClient, ['phone' => '88888888'], UploadedFile::fake()->image('segunda.jpg'));

    Storage::disk('local')->assertMissing([...$originalPaths, ...$firstPaths]);
    Storage::disk('local')->assertExists([$second->id_photo_path, $second->id_photo_thumb_path]);
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    $this->assertDatabaseHas('clients', ['id' => $client->id, 'phone' => '88888888', 'id_photo_path' => $second->id_photo_path]);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('cleans new photos when a stale client model refers to a deleted record', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->manager()->create());
    $client = Client::factory()->withIdPhoto()->create();
    $staleClient = Client::query()->findOrFail($client->id);
    app(DeleteClient::class)->handle($client);

    expect(fn () => app(UpdateClient::class)->handle($staleClient, ['phone' => '88888888'], UploadedFile::fake()->image('nueva.jpg')))
        ->toThrow(ModelNotFoundException::class);

    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseMissing('audit_logs', ['action' => 'client.updated']);
});

it('deletes current photo paths when deleting through a stale client model', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->manager()->create());
    $client = Client::factory()->withIdPhoto()->create();
    $staleClient = Client::query()->findOrFail($client->id);
    $updated = app(UpdateClient::class)->handle($client, ['full_name' => 'Nombre Actualizado'], UploadedFile::fake()->image('actualizada.jpg'));
    Storage::disk('local')->assertExists([$updated->id_photo_path, $updated->id_photo_thumb_path]);

    app(DeleteClient::class)->handle($staleClient);

    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->assertDatabaseCount('clients', 0);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'client.deleted',
        'description' => 'Cliente Nombre Actualizado ('.$updated->maskedDni().') eliminado con 0 personas autorizadas',
    ]);
});
