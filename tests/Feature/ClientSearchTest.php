<?php

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Models\User;
use App\Support\Dni;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Inertia\Testing\AssertableInertia as Assert;

it('finds clients by partial names without regard to ASCII capitalization', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => 'Lucia Cardona De Prueba']);
    Client::factory()->create(['full_name' => 'Rosa Flores De Prueba']);

    $this->actingAs($user)->get(route('clients.index', ['q' => 'cArDo']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1)
            ->where('results.data.0.client.id', $client->id)->where('clients', null)
            ->where('filters.q', 'cArDo')->where('filters.dni', null));
});

it('returns authorized people as their own results with their owner', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => 'Lucia Flores De Prueba']);
    $match = AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Rosa Cardona De Prueba']);
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Ana Castro De Prueba']);
    Client::factory()->create(['full_name' => 'Ana Mejia De Prueba']);

    $this->actingAs($user)->get(route('clients.index', ['q' => 'cArDo']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1)
            ->where('results.data.0.client.id', $client->id)->has('results.data.0.client.authorized_people', 2)
            ->where('results.data.0.person.id', $match->id));
});

it('finds exact client and authorized person phone numbers with local and international formatting', function (string $source, string $query) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['phone' => $source === 'client' ? '99999999' : '89999999']);
    $person = AuthorizedPerson::factory()->for($client)->create(['phone' => $source === 'person' ? '99999999' : '39999999']);
    Client::factory()->create(['phone' => '88888888']);

    $this->actingAs($user)->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1)
            ->where('results.data.0.client.id', $client->id)
            ->where('results.data.0.role', $source === 'person' ? 'authorized' : 'owner')->where('results.data.0.match', 'exact'));
})->with(['client', 'person'])->with(['99999999', '9999-9999', '+504 9999-9999']);

it('searches full identities by POST using only hashed and masked session data and safe URLs', function (string $source, string $input) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['dni' => $source === 'client' ? '0101198554321' : '0801199012345']);
    $person = AuthorizedPerson::factory()->for($client)->create(['dni' => $source === 'person' ? '0101198554321' : '0202199656789']);
    Client::factory()->create(['dni' => '0303197556789']);
    $this->actingAs($user);

    $redirect = $this->post(route('clients.search'), ['dni' => $input])
        ->assertRedirect(route('clients.index', ['dni' => 1]))
        ->assertSessionHas('client_search', ['hash' => Dni::hash('0101198554321'), 'masked' => '****-****-*4321']);
    $response = $this->get($redirect->headers->get('Location'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->url('/clientes?dni=1')->has('results.data', 1)
            ->where('results.data.0.client.id', $client->id)->where('filters.q', '')
            ->where('filters.dni', ['masked' => '****-****-*4321'])
            ->where('results.data.0.role', $source === 'person' ? 'authorized' : 'owner')->where('results.data.0.match', 'exact'));

    $page = Assert::fromTestResponse($response)->toArray();
    $urls = [
        $redirect->headers->get('Location'), $page['url'],
        ...array_values($response->inertiaProps('results.links')),
        ...array_values($response->inertiaProps('results.data.0.client.photo')),
        ...array_values($response->inertiaProps('results.data.0.client.authorized_people.0.photo')),
    ];
    expect(implode(' ', $urls))->not->toContain('0101198554321', '0101-1985-54321');
    expect(json_encode($response->inertiaProps()))->not->toContain(Dni::hash('0101198554321'), 'dni_hash', 'id-photos/');
})->with(['client', 'person'])->with(['0101198554321', '0101-1985-54321']);

it('removes full identities submitted accidentally through a query URL', function (string $query) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('clients.index', ['q' => $query]))
        ->assertRedirect(route('clients.index'));

    expect($response->headers->get('Location'))->not->toContain('q=', '0101198554321', '0101-1985-54321');
})->with(['0101198554321', '0101-1985-54321']);

it('does not match partial identity numbers and does not report an error', function (string $query) {
    $user = User::factory()->create();
    Client::factory()->create(['dni' => '0101198554321', 'phone' => '89999999']);

    $this->actingAs($user)->get(route('clients.index', ['q' => $query]))->assertOk()->assertSessionHasNoErrors()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 0)->where('clients', null));
})->with(['01011985', '010119855432']);

it('treats SQL LIKE wildcards as literal search characters', function (string $query) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => 'Ana De Prueba']);
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Rosa De Prueba']);

    $this->actingAs($user)->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 0)->where('clients', null));
})->with(['%', '_', '\\']);

it('paginates people in global order and preserves the query and role', function () {
    $user = User::factory()->create();
    $clients = Client::factory()->count(21)->sequence(fn (Sequence $sequence): array => [
        'full_name' => 'Titular '.chr(65 + $sequence->index).' De Prueba',
    ])->create();
    $ids = [];
    foreach ($clients as $client) {
        $ids[] = AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Persona Coincidencia'])->id;
    }
    $this->actingAs($user);

    $firstPage = $this->get(route('clients.index', ['q' => 'Coincidencia', 'rol' => 'autorizados']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 20)
            ->where('results.data.0.person.id', $ids[0])->where('results.meta.last_page', 2));
    $next = $firstPage->inertiaProps('results.links.next');
    expect($next)->toContain('q=Coincidencia', 'rol=autorizados', 'page=2');
    $this->get($next)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 1)
            ->where('results.data.0.person.id', $ids[20])->where('filters.q', 'Coincidencia'));
});

it('ignores search queries that are not strings or exceed one hundred characters', function (string $kind) {
    $user = User::factory()->create();
    Client::factory()->count(2)->create();
    $query = $kind === 'array' ? ['invalid'] : str_repeat('a', 101);

    $this->actingAs($user)->followingRedirects()->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 2)->where('filters.q', '')
            ->where('filters.dni', null)->where('results', null));
})->with(['array', 'overlong']);

it('requires a full normalized identity when using identity search', function (string $input) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('clients.search'), ['dni' => $input])
        ->assertSessionHasErrors('dni')->assertSessionMissing('client_search');
})->with(['', '010119855432', 'texto']);

it('requires authentication for identity search', function () {
    $this->post(route('clients.search'), ['dni' => '0101198554321'])
        ->assertRedirect(route('login'))->assertSessionMissing('client_search');
});

it('removes identities hidden in malformed query arrays from response URLs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('clients.index', ['q' => ['0101198554321']]))
        ->assertRedirect(route('clients.index'));

    expect($response->headers->get('Location'))->not->toContain('0101198554321');
    $clean = $this->get($response->headers->get('Location'))->assertOk();
    expect(Assert::fromTestResponse($clean)->toArray()['url'])->not->toContain('0101198554321');
});

it('orders owners before authorized people and ranks exact prefix and partial names', function () {
    $user = User::factory()->create();
    $owner = Client::factory()->create(['full_name' => 'Ana Reyes']);
    Client::factory()->create(['full_name' => 'Mariana Paz']);
    foreach (['Ana Reyes', 'Ana Lopez', 'Susana Diaz'] as $name) {
        AuthorizedPerson::factory()->for($owner)->create(['full_name' => $name]);
    }

    $response = $this->actingAs($user)->get(route('clients.index', ['q' => 'ana']))->assertOk();
    $results = $response->inertiaProps('results.data');
    expect(array_map(fn ($row) => $row['person']['full_name'] ?? $row['client']['full_name'], $results))
        ->toBe(['Ana Reyes', 'Mariana Paz', 'Ana Lopez', 'Ana Reyes', 'Susana Diaz']);
    expect(array_column($results, 'match'))->toBe(['prefix', 'partial', 'prefix', 'prefix', 'partial']);
    expect($results[0]['person'])->toBeNull();

    $this->get(route('clients.index', ['q' => 'ana reyes']))->assertInertia(fn (Assert $page) => $page
        ->has('results.data', 2)->where('results.data.0.role', 'owner')->where('results.data.0.match', 'exact')
        ->where('results.data.1.role', 'authorized')->where('results.data.1.match', 'exact'));
});

it('normalizes accents case and whitespace and never exposes the search column', function (string $query) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => '  María   Reyes  ']);
    $person = AuthorizedPerson::factory()->for($client)->create(['full_name' => 'María López']);

    $response = $this->actingAs($user)->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', 2));

    expect(json_encode($response->inertiaProps()))->not->toContain('search_name');
    expect($client->search_name)->toBe('maria reyes');
    $client->update(['full_name' => 'Óscar Cruz']);
    $person->update(['full_name' => 'Óscar López']);
    $this->assertDatabaseHas('clients', ['id' => $client->id, 'search_name' => 'oscar cruz']);
    $this->assertDatabaseHas('authorized_people', ['id' => $person->id, 'search_name' => 'oscar lopez']);
})->with(['maria', 'MARÍA']);

it('filters roles without changing counts', function (string $role, int $expected, string $normalized) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => 'Ana Reyes']);
    AuthorizedPerson::factory()->for($client)->count(2)->create(['full_name' => 'Ana Lopez']);

    $response = $this->actingAs($user)->get(route('clients.index', ['q' => 'ana', 'rol' => $role]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('results.data', $expected)
            ->where('counts', ['owners' => 1, 'authorized' => 2])->where('filters.role', $normalized));
    if ($role === 'duenos' || $role === 'autorizados') {
        expect(array_unique(array_column($response->inertiaProps('results.data'), 'role')))
            ->toBe([$role === 'duenos' ? 'owner' : 'authorized']);
    }
})->with([['duenos', 1, 'duenos'], ['autorizados', 2, 'autorizados'], ['basura', 3, 'todos'], ['todos', 3, 'todos']]);

it('uses listing mode without text except for the authorized filter', function (string $role) {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Zulema Prueba']);
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Ana Prueba']);

    $response = $this->actingAs($user)->get(route('clients.index', ['rol' => $role]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('counts', ['owners' => 1, 'authorized' => 2]));
    if ($role === 'autorizados') {
        $response->assertInertia(fn (Assert $page) => $page->where('clients', null)->has('results.data', 2)
            ->where('results.data.0.person.full_name', 'Ana Prueba')->where('results.data.1.person.full_name', 'Zulema Prueba'));
    } else {
        $response->assertInertia(fn (Assert $page) => $page->where('results', null)->has('clients.data', 1));
        expect($response->inertiaProps('clients.links.next'))->toBeNull();
    }
})->with(['todos', 'duenos', 'autorizados']);

it('returns both roles with exact rank for the same phone or identity', function (string $kind) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['phone' => '99999999', 'dni' => '0101198554321']);
    $other = Client::factory()->create(['phone' => '88888888']);
    AuthorizedPerson::factory()->for($other)->create(['phone' => '99999999', 'dni' => '0101198554321']);
    $this->actingAs($user);

    if ($kind === 'identity') {
        $this->post(route('clients.search'), ['dni' => '0101198554321'])->assertRedirect();
    }
    $this->get(route('clients.index', $kind === 'identity' ? ['dni' => 1] : ['q' => '99999999']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('results.data', 2)
        ->where('results.data.0.client.id', $client->id)->where('results.data.0.role', 'owner')
        ->where('results.data.0.match', 'exact')->where('results.data.1.role', 'authorized')
        ->where('results.data.1.match', 'exact')->where('results.data.1.client.id', $other->id));
})->with(['phone', 'identity']);

it('backfills existing rows when adding search names', function () {
    $client = Client::factory()->create(['full_name' => '  María   Reyes ']);
    $person = AuthorizedPerson::factory()->for($client)->create(['full_name' => 'ÓSCAR López']);
    $migration = require database_path('migrations/2026_11_13_203036_add_search_name_to_clients_and_authorized_people.php');
    $migration->down();

    $migration->up();

    $this->assertDatabaseHas('clients', ['id' => $client->id, 'search_name' => 'maria reyes']);
    $this->assertDatabaseHas('authorized_people', ['id' => $person->id, 'search_name' => 'oscar lopez']);
});
