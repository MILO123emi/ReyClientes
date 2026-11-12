<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetUserPassword
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(User $user, string $password, bool $mustChange): void
    {
        DB::transaction(function () use ($user, $password, $mustChange): void {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => $mustChange,
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            $this->recordAudit->handle(
                AuditAction::UserPasswordSetByOwner,
                "Contraseña asignada a {$user->name} ({$user->email})",
                $user,
            );
        });
    }
}
