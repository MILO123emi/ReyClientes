<?php

use App\Models\Client;
use App\Models\User;
use App\Support\Dni;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores an encrypted normalized identity and its HMAC', function () {
    $client = Client::factory()->create(['dni' => '0801-1990-12345']);
    $raw = DB::table('clients')->where('id', $client->id)->value('dni');

    expect($raw)->not->toBe('0801199012345')->not->toContain('0801199012345')->not->toContain('0801-1990-12345');
    expect($client->refresh()->dni)->toBe('0801199012345');
    expect($client->dni_hash)->toBe(Dni::hash('0801199012345'));
    expect($client->maskedDni())->toBe('****-****-*2345');
});

it('finds a client by a formatted identity and refreshes its hash after editing', function () {
    $client = Client::factory()->create(['dni' => '0801-1990-12345']);

    expect(Client::query()->byDni('0801-1990-12345')->sole()->is($client))->toBeTrue();
    $client->update(['dni' => '0101-1985-54321']);

    expect($client->refresh()->dni)->toBe('0101198554321');
    expect($client->dni_hash)->toBe(Dni::hash('0101198554321'));
    expect(Client::query()->byDni('0801199012345')->exists())->toBeFalse();
    expect(Client::query()->byDni('0101-1985-54321')->sole()->is($client))->toBeTrue();
});

it('rejects a duplicate client identity even when formatted differently', function () {
    Client::factory()->create(['dni' => '0801199012345']);

    expect(fn () => Client::factory()->create(['dni' => '0801-1990-12345']))->toThrow(QueryException::class);
    $this->assertDatabaseCount('clients', 1);
});

it('hides identity and photo paths from serialization', function () {
    $client = Client::factory()->create();

    expect($client->refresh()->toArray())->toHaveKeys(['id', 'full_name', 'phone', 'created_by', 'updated_by', 'created_at', 'updated_at'])
        ->toHaveCount(7);
});

it('protects the HMAC and attribution fields from mass assignment', function () {
    $client = Client::factory()->create();
    $hash = $client->dni_hash;

    $client->fill(['dni_hash' => 'forged', 'created_by' => 123, 'updated_by' => 123])->save();

    expect($client->refresh()->dni_hash)->toBe($hash);
    expect($client->created_by)->toBeNull();
    expect($client->updated_by)->toBeNull();
});

it('retains clients and clears attribution when the author is deleted', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);
    expect($client->creator->is($user))->toBeTrue();
    expect($client->updater->is($user))->toBeTrue();

    $user->delete();

    $this->assertModelExists($client);
    expect($client->refresh()->created_by)->toBeNull();
    expect($client->updated_by)->toBeNull();
});
