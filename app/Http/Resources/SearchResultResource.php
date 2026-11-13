<?php

namespace App\Http\Resources;

use App\Models\AuthorizedPerson;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array{role: string, match: string, client: Client, person: AuthorizedPerson|null} $resource */
class SearchResultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $client = $this->resource['client'];
        $person = $this->resource['person'];

        return [
            'key' => $person !== null ? 'person-'.$person->id : 'client-'.$client->id,
            'role' => $this->resource['role'],
            'match' => $this->resource['match'],
            'client' => new ClientResource($client),
            'person' => $person !== null ? new AuthorizedPersonResource($person) : null,
        ];
    }
}
