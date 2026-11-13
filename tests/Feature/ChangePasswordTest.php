<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('requires authentication to view or change a password', function (string $method, string $uri) {
    $this->call($method, $uri)->assertRedirect(route('login'));
})->with([['GET', '/cambiar-contrasena'], ['PUT', '/cambiar-contrasena'], ['GET', '/settings/password']]);

it('redirects users with temporary passwords until they change them', function (string $uri) {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->get($uri)->assertRedirect(route('password.change'));
})->with(['/clientes', '/', '/settings/password']);

it('allows temporary-password users to view the notice and logout', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->get(route('password.change'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ChangePassword')->where('mustChange', true));
    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

it('shows the same password form for users without a temporary password', function () {
    $this->actingAs(User::factory()->create())->get(route('password.change'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ChangePassword')->where('mustChange', false));
});

it('updates an eight-character password, clears the flag and grants access to the clients page', function () {
    $user = User::factory()->mustChangePassword()->create();
    $oldHash = $user->password;

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'password', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh',
        'name' => 'Nombre no autorizado', 'email' => 'unwanted@example.com', 'is_owner' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('clients.index'));

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and($user->password)->not->toBe($oldHash)
        ->and(Hash::check('abcdefgh', $user->password))->toBeTrue()
        ->and($user->isOwner())->toBeFalse();
    $this->assertDatabaseMissing('users', ['id' => $user->id, 'email' => 'unwanted@example.com']);
    $this->assertDatabaseMissing('users', ['id' => $user->id, 'name' => 'Nombre no autorizado']);
    $this->get(route('clients.index'))->assertOk();
});

it('leaves the password and temporary flag unchanged when validation fails', function (array $overrides, string $field) {
    $user = User::factory()->mustChangePassword()->create();
    $hash = $user->password;

    $this->actingAs($user)->from(route('password.change'))->put(route('password.change.update'), array_replace([
        'current_password' => 'password', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh',
    ], $overrides))->assertSessionHasErrors($field)->assertRedirect(route('password.change'));

    expect($user->refresh()->password)->toBe($hash)->and($user->must_change_password)->toBeTrue();
})->with([
    'actual ausente' => [['current_password' => ''], 'current_password'],
    'actual incorrecta' => [['current_password' => 'incorrecta'], 'current_password'],
    'confirmación distinta' => [['password_confirmation' => 'diferente'], 'password'],
    'siete caracteres' => [['password' => '1234567', 'password_confirmation' => '1234567'], 'password'],
]);

it('permits reusing the current password even when the change is forced', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->put(route('password.change.update'), [
        'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('clients.index'));

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('uses the same eight-character minimum in production without external checks', function () {
    $this->app->detectEnvironment(fn () => 'production');
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $this->withSession(['_token' => 'test-token'])->actingAs($user)->put(route('password.change.update'), [
        '_token' => 'test-token',
        'current_password' => 'password', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh',
    ])->assertSessionHasNoErrors()->assertRedirect(route('clients.index'));

    expect(Hash::check('abcdefgh', $user->refresh()->password))->toBeTrue();
});

it('redirects the legacy password URL and removes all remaining settings pages', function () {
    $this->actingAs(User::factory()->create())->get('/settings/password')->assertRedirect(route('password.change'));
    $this->get('/settings/security')->assertNotFound();
    $this->get('/settings')->assertNotFound();
});

it('throttles the password change endpoint after six requests', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->put(route('password.change.update'), [])->assertSessionHasErrors('current_password');
    }

    $this->put(route('password.change.update'), [])->assertTooManyRequests();
});
