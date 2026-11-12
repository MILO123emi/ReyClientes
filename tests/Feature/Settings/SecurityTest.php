<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('requires login to view or change the password', function (string $method, string $uri) {
    $this->call($method, $uri)->assertRedirect(route('login'));
})->with([
    ['GET', '/settings/security'],
    ['PUT', '/settings/password'],
]);

it('shows only password settings without exposing sensitive user attributes', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('auth.user.id', $user->id)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('canManageTwoFactor')
            ->missing('passkeys'));
});

it('updates the password when the current password is correct', function () {
    $user = User::factory()->create();
    $originalName = $user->name;

    $this->actingAs($user)->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'name' => 'Nombre no autorizado',
            'email' => 'unwanted@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    expect($user->name)->toBe($originalName);
    $this->assertDatabaseMissing('users', ['id' => $user->id, 'email' => 'unwanted@example.com']);
});

it('keeps the password unchanged when validation fails', function (array $overrides, string $field) {
    $user = User::factory()->create();
    $originalHash = $user->password;

    $this->actingAs($user)->from(route('security.edit'))
        ->put(route('user-password.update'), array_replace([
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ], $overrides))
        ->assertSessionHasErrors($field)
        ->assertRedirect(route('security.edit'));

    expect($user->refresh()->password)->toBe($originalHash);
})->with([
    'missing current password' => [['current_password' => ''], 'current_password'],
    'incorrect current password' => [['current_password' => 'incorrect'], 'current_password'],
    'short new password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatched confirmation' => [['password_confirmation' => 'different-password'], 'password'],
]);

it('redirects settings to the temporary password page', function () {
    $this->actingAs(User::factory()->create())->get('/settings')
        ->assertRedirect('/settings/security');
});
