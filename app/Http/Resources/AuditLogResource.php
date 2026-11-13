<?php

namespace App\Http\Resources;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['created_at' => $this->created_at->toIso8601String(), 'user' => $this->user->name ?? '—', 'action_label' => AuditAction::from($this->action)->label(), 'description' => $this->description, 'ip_address' => $this->ip_address];
    }
}
