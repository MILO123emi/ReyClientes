<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateUserPermission
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $user, bool $enabled): void
    {
        DB::transaction(function () use ($user, $enabled): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->isOwner()) {
                throw new DomainException('No se puede modificar a la administradora principal.');
            }
            if ($user->can_manage_cards === $enabled) {
                return;
            }
            $user->forceFill(['can_manage_cards' => $enabled])->save();
            $status = $enabled ? 'activado' : 'desactivado';
            $this->audit->handle(AuditAction::UserPermissionChanged, "Permiso de gestión {$status} para {$user->name}", $user);
        });
    }
}
