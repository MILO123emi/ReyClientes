<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\DeleteClient;
use App\Actions\Clients\UpdateClient;
use App\Enums\Relationship;
use App\Http\Requests\Clients\DeleteClientRequest;
use App\Http\Requests\Clients\SearchClientRequest;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Queries\ClientSearch;
use App\Support\Dni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request, ClientSearch $search): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Client::class);
        $input = $request->query('q');
        if ($input !== null && ! is_string($input)) {
            return to_route('clients.index');
        }
        if (is_string($input) && (strlen(Dni::normalize($input)) === 13 || preg_match('/\d{4}\D?\d{4}\D?\d{5}/', $input))) {
            return to_route('clients.index');
        }

        $text = is_string($input) && Str::length($input) <= 100 ? trim($input) : '';
        $stored = $request->session()->get('client_search');
        $dni = $request->boolean('dni') && is_array($stored) && isset($stored['hash'], $stored['masked']) ? $stored : null;
        $hash = $dni !== null && is_string($dni['hash']) ? $dni['hash'] : null;
        $query = Client::query()->with('authorizedPeople')->withCount('authorizedPeople')->orderBy('full_name')->orderBy('id');
        $search->apply($query, $text, $hash);
        $clients = $query->paginate(20)->appends($hash !== null ? ['dni' => 1] : ($text !== '' ? ['q' => $text] : []));
        $matchedPersonIds = $search->matchedPersonIds($clients->getCollection(), $text, $hash);

        return Inertia::render('clients/Index', [
            'clients' => ClientResource::collection($clients),
            'relationships' => Relationship::options(),
            'filters' => ['q' => $hash === null ? $text : '', 'dni' => $hash !== null ? ['masked' => $dni['masked']] : null],
            'matchedPersonIds' => $matchedPersonIds,
        ]);
    }

    public function search(SearchClientRequest $request): RedirectResponse
    {
        $dni = $request->validated('dni');
        $request->session()->put('client_search', ['hash' => Dni::hash($dni), 'masked' => Dni::mask($dni)]);

        return to_route('clients.index', ['dni' => 1]);
    }

    public function store(StoreClientRequest $request, CreateClient $create): RedirectResponse
    {
        /** @var UploadedFile $photo */
        $photo = $request->file('id_photo');
        $create->handle($request->payload(), $photo);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente guardado']);

        return back();
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $update): RedirectResponse
    {
        /** @var UploadedFile|null $photo */
        $photo = $request->file('id_photo');
        $update->handle($client, $request->payload(), $photo);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente guardado']);

        return back();
    }

    public function destroy(DeleteClientRequest $request, Client $client, DeleteClient $delete): RedirectResponse
    {
        $delete->handle($client);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente eliminado']);

        return back();
    }
}
