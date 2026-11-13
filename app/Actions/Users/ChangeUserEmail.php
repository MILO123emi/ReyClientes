<?php

namespace App\Actions\Users;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\EmailChangeRequest;
use App\Models\PasswordResetCode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChangeUserEmail
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * @param  string|null  $keepSessionId  Session that stays open; every other session of the user is closed.
     */
    public function handle(User $user, string $email, ?string $keepSessionId = null, bool $fromConsole = false): void
    {
        $email = Str::lower(trim($email));

        DB::transaction(function () use ($user, $email, $keepSessionId, $fromConsole): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (User::query()->where('email', $email)->whereKeyNot($locked->id)->exists()) {
                throw new DomainException('Ese correo ya lo usa otra cuenta.');
            }

            $previous = $locked->email;
            $locked->forceFill(['email' => $email, 'remember_token' => Str::random(60)])->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            // Codes issued for the previous address must not outlive it.
            EmailChangeRequest::query()->where('user_id', $locked->id)->delete();
            PasswordResetCode::query()->where('user_id', $locked->id)->delete();
            DB::table('sessions')->where('user_id', $locked->id)
                ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();

            $origin = $fromConsole ? ' (desde la consola del servidor)' : '';
            $this->recordAudit->handle(
                AuditAction::UserEmailChanged,
                "Correo de {$locked->name} cambiado de {$previous} a {$email}{$origin}",
                $locked,
            );
        });
    }
}
