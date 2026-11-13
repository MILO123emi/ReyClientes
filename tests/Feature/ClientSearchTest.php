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
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)
            ->where('clients.data.0.id', $client->id)->where('matchedPersonIds', [])
            ->where('filters.q', 'cArDo')->where('filters.dni', null));
});

it('returns the titular and highlights only the authorized people whose names match', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => 'Lucia Flores De Prueba']);
    $match = AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Rosa Cardona De Prueba']);
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Ana Castro De Prueba']);
    Client::factory()->create(['full_name' => 'Ana Mejia De Prueba']);

    $this->actingAs($user)->get(route('clients.index', ['q' => 'cArDo']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)
            ->where('clients.data.0.id', $client->id)->has('clients.data.0.authorized_people', 2)
            ->where('matchedPersonIds', [$match->id]));
});

it('finds exact client and authorized person phone numbers with local and international formatting', function (string $source, string $query) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['phone' => $source === 'client' ? '99999999' : '89999999']);
    $person = AuthorizedPerson::factory()->for($client)->create(['phone' => $source === 'person' ? '99999999' : '39999999']);
    Client::factory()->create(['phone' => '88888888']);

    $this->actingAs($user)->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)
            ->where('clients.data.0.id', $client->id)
            ->where('matchedPersonIds', $source === 'person' ? [$person->id] : []));
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
        ->assertInertia(fn (Assert $page) => $page->url('/clientes?dni=1')->has('clients.data', 1)
            ->where('clients.data.0.id', $client->id)->where('filters.q', '')
            ->where('filters.dni', ['masked' => '****-****-*4321'])
            ->where('matchedPersonIds', $source === 'person' ? [$person->id] : []));

    $page = Assert::fromTestResponse($response)->toArray();
    $urls = [
        $redirect->headers->get('Location'), $page['url'],
        ...array_values($response->inertiaProps('clients.links')),
        ...array_values($response->inertiaProps('clients.data.0.photo')),
        ...array_values($response->inertiaProps('clients.data.0.authorized_people.0.photo')),
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
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 0)->where('matchedPersonIds', []));
})->with(['01011985', '010119855432']);

it('treats SQL LIKE wildcards as literal search characters', function (string $query) {
    $user = User::factory()->create();
    $client = Client::factory()->create(['full_name' => 'Ana De Prueba']);
    AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Rosa De Prueba']);

    $this->actingAs($user)->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 0)->where('matchedPersonIds', []));
})->with(['%', '_', '\\']);

it('limits matched people to the current page and preserves the text query in pagination', function () {
    $user = User::factory()->create();
    $clients = Client::factory()->count(21)->sequence(fn (Sequence $sequence): array => [
        'full_name' => 'Titular '.chr(65 + $sequence->index).' De Prueba',
    ])->create();
    $ids = [];
    foreach ($clients as $client) {
        $ids[] = AuthorizedPerson::factory()->for($client)->create(['full_name' => 'Persona Coincidencia'])->id;
    }
    $this->actingAs($user);

    $firstPage = $this->get(route('clients.index', ['q' => 'Coincidencia']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 20)
            ->where('matchedPersonIds', array_slice($ids, 0, 20))->where('clients.meta.last_page', 2));
    $next = $firstPage->inertiaProps('clients.links.next');
    expect($next)->toContain('q=Coincidencia', 'page=2');
    $this->get($next)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)
            ->where('matchedPersonIds', [$ids[20]])->where('filters.q', 'Coincidencia'));
});

it('ignores search queries that are not strings or exceed one hundred characters', function (string $kind) {
    $user = User::factory()->create();
    Client::factory()->count(2)->create();
    $query = $kind === 'array' ? ['invalid'] : str_repeat('a', 101);

    $this->actingAs($user)->followingRedirects()->get(route('clients.index', ['q' => $query]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('clients.data', 2)->where('filters.q', '')
            ->where('filters.dni', null)->where('matchedPersonIds', []));
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
