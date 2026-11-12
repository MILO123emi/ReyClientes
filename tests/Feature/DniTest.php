<?php

use App\Support\Dni;

it('normalizes formats and masks fictional identities', function () {
    expect(Dni::normalize(' 0801-1990-12345 '))->toBe('0801199012345');
    expect(Dni::normalize('abc0801.1990/12345'))->toBe('0801199012345');
    expect(Dni::format('0801199012345'))->toBe('0801-1990-12345');
    expect(Dni::format('0801-1990-12345'))->toBe('0801-1990-12345');
    expect(Dni::mask('0801-1990-12345'))->toBe('****-****-*2345');
});

it('hashes normalized identities deterministically with a dedicated key', function () {
    config()->set('reypass.dni_hash_key', 'testing-dni-hash-key');
    $hash = Dni::hash('0801199012345');

    expect($hash)->toMatch('/^[a-f0-9]{64}$/')
        ->toBe(hash_hmac('sha256', '0801199012345', 'testing-dni-hash-key'))
        ->toBe(Dni::hash('0801-1990-12345'));

    config()->set('reypass.dni_hash_key', 'another-testing-key');
    expect(Dni::hash('0801199012345'))->not->toBe($hash);
});

it('rejects a missing key without exposing the identity', function (?string $key) {
    config()->set('reypass.dni_hash_key', $key);

    expect(fn () => Dni::hash('0801199012345'))->toThrow(function (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('DNI_HASH_KEY no está configurada.')
            ->not->toContain('0801199012345');
    });
})->with([null, '', '   ']);
