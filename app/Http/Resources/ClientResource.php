<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Support\Dni;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'dni' => Dni::format($this->dni),
            'phone' => Phone::format($this->phone),
            'photo' => [
                'thumb' => route('media.show', ['type' => 'client', 'id' => $this->id, 'variant' => 'thumb']),
                'full' => route('media.show', ['type' => 'client', 'id' => $this->id, 'variant' => 'full']),
            ],
            'people_count' => $this->whenCounted('authorizedPeople'),
            'authorized_people' => AuthorizedPersonResource::collection($this->whenLoaded('authorizedPeople')),
        ];
    }
}
