<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->isOwner()) {
                throw new DomainException('No se puede eliminar a la administradora principal.');
            }
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $description = "Usuario {$user->name} ({$user->email}) eliminado";
            $user->delete();
            $this->audit->handle(AuditAction::UserDeleted, $description, $user);
        });
    }
}
