<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('returns 404 for removed endpoints for guests and authenticated users', function (string $method, string $uri, bool $authenticated) {
    if ($authenticated) {
        $this->actingAs(User::factory()->create());
    }

    $this->call($method, $uri)->assertNotFound();
})->with([
    'registration form' => ['GET', '/register'],
    'registration submission' => ['POST', '/register'],
    'reset link form' => ['GET', '/forgot-password'],
    'reset link request' => ['POST', '/forgot-password'],
    'reset token form' => ['GET', '/reset-password/example'],
    'reset token submission' => ['POST', '/reset-password'],
    'email verification' => ['GET', '/email/verify'],
    'email verification link' => ['GET', '/email/verify/1/example'],
    'verification notification' => ['POST', '/email/verification-notification'],
    'two factor challenge' => ['GET', '/two-factor-challenge'],
    'two factor login' => ['POST', '/two-factor-challenge'],
    'two factor settings' => ['GET', '/settings/two-factor'],
    'enable two factor' => ['POST', '/user/two-factor-authentication'],
    'confirm two factor' => ['POST', '/user/confirmed-two-factor-authentication'],
    'disable two factor' => ['DELETE', '/user/two-factor-authentication'],
    'two factor QR' => ['GET', '/user/two-factor-qr-code'],
    'two factor secret' => ['GET', '/user/two-factor-secret-key'],
    'two factor recovery codes' => ['GET', '/user/two-factor-recovery-codes'],
    'regenerate recovery codes' => ['POST', '/user/two-factor-recovery-codes'],
    'passkey discovery' => ['GET', '/.well-known/passkey-endpoints'],
    'passkey login options' => ['GET', '/passkeys/login/options'],
    'passkey login' => ['POST', '/passkeys/login'],
    'passkey confirm options' => ['GET', '/passkeys/confirm/options'],
    'passkey confirmation' => ['POST', '/passkeys/confirm'],
    'passkey registration options' => ['GET', '/user/passkeys/options'],
    'passkey registration' => ['POST', '/user/passkeys'],
    'passkey deletion' => ['DELETE', '/user/passkeys/1'],
    'profile settings' => ['GET', '/settings/profile'],
    'profile update' => ['PATCH', '/settings/profile'],
    'account deletion' => ['DELETE', '/settings/profile'],
    'appearance settings' => ['GET', '/settings/appearance'],
    'cached password confirmation' => ['GET', '/user/confirm-password'],
    'cached password confirmation submission' => ['POST', '/user/confirm-password'],
    'default private storage download' => ['GET', '/storage/id-photos/example.jpg'],
])->with([false, true]);

it('creates the database without unused recovery, passkey tables or two factor columns', function () {
    expect(Schema::hasTable('password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasTable('passkeys'))->toBeFalse()
        ->and(Schema::hasColumns('users', ['two_factor_secret']))->toBeFalse()
        ->and(Schema::hasColumns('users', ['two_factor_recovery_codes']))->toBeFalse()
        ->and(Schema::hasColumns('users', ['two_factor_confirmed_at']))->toBeFalse();
});

it('ignores stale dark appearance preferences on login', function () {
    $this->withCookie('appearance', 'dark')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="es">', escape: false)
        ->assertSee('name="color-scheme" content="light"', escape: false)
        ->assertDontSee('class="dark"', escape: false)
        ->assertDontSee('prefers-color-scheme');
});
