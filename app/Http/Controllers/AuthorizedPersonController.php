<?php

namespace App\Http\Controllers;

use App\Actions\People\CreateAuthorizedPerson;
use App\Actions\People\DeleteAuthorizedPerson;
use App\Actions\People\UpdateAuthorizedPerson;
use App\Http\Requests\People\DeleteAuthorizedPersonRequest;
use App\Http\Requests\People\StoreAuthorizedPersonRequest;
use App\Http\Requests\People\UpdateAuthorizedPersonRequest;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class AuthorizedPersonController extends Controller
{
    public function store(StoreAuthorizedPersonRequest $request, Client $client, CreateAuthorizedPerson $create): RedirectResponse
    {
        /** @var UploadedFile $photo */
        $photo = $request->file('id_photo');
        $create->handle($client, $request->payload(), $photo);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona autorizada guardada']);

        return back();
    }

    public function update(UpdateAuthorizedPersonRequest $request, AuthorizedPerson $person, UpdateAuthorizedPerson $update): RedirectResponse
    {
        /** @var UploadedFile|null $photo */
        $photo = $request->file('id_photo');
        $update->handle($person, $request->payload(), $photo);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona autorizada guardada']);

        return back();
    }

    public function destroy(DeleteAuthorizedPersonRequest $request, AuthorizedPerson $person, DeleteAuthorizedPerson $delete): RedirectResponse
    {
        $delete->handle($person);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona autorizada eliminada']);

        return back();
    }
}
