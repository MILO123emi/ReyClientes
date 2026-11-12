<?php

namespace App\Actions\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordAudit
{
    public function handle(AuditAction $action, string $description, ?Model $subject = null, ?User $actor = null): AuditLog
    {
        if (preg_match('/\d{4}\D?\d{4}\D?\d{5}/', $description)) {
            throw new InvalidArgumentException('La descripción de auditoría no puede incluir una identidad completa.');
        }

        $actor ??= auth()->user();
        $log = new AuditLog;
        $log->forceFill([
            'user_id' => $actor?->id,
            'action' => $action->value,
            'description' => Str::limit($description, 255, ''),
            'ip_address' => request()->ip(),
        ]);
        $log->subject()->associate($subject);
        $log->save();

        return $log;
    }
}
