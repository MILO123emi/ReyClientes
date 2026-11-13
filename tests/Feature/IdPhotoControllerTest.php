<?php

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

it('redirects guests to login before serving photos', function () {
    Storage::fake('local');

    $this->get('/media/client/1/thumb')->assertRedirect(route('login'));
});

it('serves private JPEG photos to every authenticated role for both owner types', function (string $role, string $type, string $model) {
    Storage::fake('local');
    $user = match ($role) {
        'owner' => User::factory()->owner()->create(),
        'manager' => User::factory()->manager()->create(),
        default => User::factory()->create(),
    };
    $record = $model::factory()->withIdPhoto()->create();

    $response = $this->actingAs($user)->get(route('media.show', ['type' => $type, 'id' => $record->id, 'variant' => 'full']));

    $response->assertOk()->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Disposition', 'inline');
    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    expect(getimagesizefromstring($response->getContent())['mime'])->toBe('image/jpeg');
    expect($response->getContent())->not->toContain("Exif\0\0");
})->with(['reader', 'manager', 'owner'])
    ->with(['client' => ['client', Client::class], 'person' => ['person', AuthorizedPerson::class]]);

it('serves different full and thumbnail image sizes', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $client = Client::factory()->withIdPhoto()->create();
    $this->actingAs($user);

    $full = $this->get('/media/client/'.$client->id.'/full')->assertOk();
    $thumb = $this->get('/media/client/'.$client->id.'/thumb')->assertOk();

    expect(array_slice(getimagesizefromstring($full->getContent()), 0, 2))->toBe([1000, 630]);
    expect(array_slice(getimagesizefromstring($thumb->getContent()), 0, 2))->toBe([320, 202]);
});

it('returns 404 for invalid parameters absent records or absent files', function (string $path) {
    Storage::fake('local');
    $user = User::factory()->create();
    Client::factory()->create();

    $this->actingAs($user)->get($path)->assertNotFound();
})->with([
    'user type' => ['/media/user/1/full'],
    'unknown type' => ['/media/foo/1/full'],
    'unknown variant' => ['/media/client/1/invalid'],
    'non numeric id' => ['/media/client/abc/full'],
    'missing model' => ['/media/client/999999/full'],
    'missing full file' => ['/media/client/1/full'],
    'missing thumbnail file' => ['/media/client/1/thumb'],
]);

it('redirects users who must change their password', function () {
    Storage::fake('local');
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->get('/media/client/1/thumb')->assertRedirect(route('password.change'));
});

it('enforces the view authorization before returning a photo', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $client = Client::factory()->withIdPhoto()->create();
    Gate::before(fn (User $actor, string $ability): ?bool => $ability === 'view' ? false : null);

    $this->actingAs($user)->get('/media/client/'.$client->id.'/full')->assertForbidden();
});

it('generates the specified media route', function () {
    expect(route('media.show', ['type' => 'client', 'id' => 1, 'variant' => 'thumb'], absolute: false))
        ->toBe('/media/client/1/thumb');
});
