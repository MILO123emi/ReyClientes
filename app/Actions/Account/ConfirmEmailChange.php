<?php

namespace App\Actions\Account;

use App\Actions\Audit\RecordAudit;
use App\Actions\Users\ChangeUserEmail;
use App\Enums\AuditAction;
use App\Models\EmailChangeRequest;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class ConfirmEmailChange
{
    public function __construct(private RecordAudit $audit, private ChangeUserEmail $changeEmail) {}

    public function handle(User $user, #[SensitiveParameter] string $code, ?string $keepSessionId = null): void
    {
        $error = DB::transaction(function () use ($user, $code, $keepSessionId): ?string {
            $pending = EmailChangeRequest::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($pending === null || ! $pending->isUsable()) {
                return 'El código venció o ya no es válido. Pide uno nuevo.';
            }

            if (! Hash::check($code, $pending->code_hash)) {
                $pending->attempts++;
                $pending->save();
                $this->audit->handle(AuditAction::UserEmailChangeFailed, "Código incorrecto al cambiar el correo de {$user->name}", $user);

                return 'El código no es correcto.';
            }

            try {
                $this->changeEmail->handle($user, $pending->new_email, $keepSessionId);
            } catch (DomainException $exception) {
                $pending->delete();

                return $exception->getMessage();
            }

            return null;
        });

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }
    }
}
