<?php

namespace App\Http\Resources;

use App\Models\AuthorizedPerson;
use App\Support\Dni;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuthorizedPerson */
class AuthorizedPersonResource extends JsonResource
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
                'thumb' => route('media.show', ['type' => 'person', 'id' => $this->id, 'variant' => 'thumb']),
                'full' => route('media.show', ['type' => 'person', 'id' => $this->id, 'variant' => 'full']),
            ],
            'client_id' => $this->client_id,
            'relationship' => $this->relationship->value,
            'relationship_label' => $this->relationshipLabel(),
            'relationship_other' => $this->relationship_other,
        ];
    }
}
