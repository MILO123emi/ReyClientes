<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Route;

it('renders dark mode only for the unencrypted dark preference', function (?string $appearance, bool $dark) {
    if ($appearance !== null) {
        $this->withUnencryptedCookie('appearance', $appearance);
    }
    $response = $this->get(route('login'))->assertOk();
    if ($dark) {
        $response->assertSee('<html lang="es" class="dark">', escape: false);
    } else {
        $response->assertDontSee('class="dark"', escape: false);
    }
    expect(app(EncryptCookies::class)->isDisabled('appearance'))->toBeTrue();
})->with([[null, false], ['light', false], ['dark', true], ['unexpected', false]]);

it('leaves the appearance cookie readable on outgoing responses', function () {
    Route::middleware('web')->get('/_tests/appearance', fn () => response('OK')->cookie('appearance', 'dark'));
    $this->get('/_tests/appearance')->assertOk()->assertPlainCookie('appearance', 'dark');
});
