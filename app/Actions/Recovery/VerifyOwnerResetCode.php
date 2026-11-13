<?php

namespace App\Actions\Recovery;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class VerifyOwnerResetCode
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $email, #[SensitiveParameter] string $code, #[SensitiveParameter] string $password): void
    {
        $error = DB::transaction(function () use ($email, $code, $password): ?string {
            $owner = User::query()->where('email', Str::lower(trim($email)))->where('is_owner', true)->lockForUpdate()->first();
            if ($owner === null) {
                return 'El código venció o ya no es válido. Pide uno nuevo.';
            }

            $reset = PasswordResetCode::query()->where('user_id', $owner->id)->orderByDesc('id')->lockForUpdate()->first();
            if ($reset === null || $reset->used_at !== null || $reset->expires_at->lessThanOrEqualTo(now()) || $reset->attempts >= 5) {
                return 'El código venció o ya no es válido. Pide uno nuevo.';
            }

            if (! Hash::check($code, $reset->code_hash)) {
                $reset->attempts++;
                $reset->save();
                $this->audit->handle(AuditAction::AuthResetCodeFailed, 'Código de recuperación incorrecto para la cuenta principal', $owner);

                return 'El código no es correcto.';
            }

            $owner->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
            $reset->used_at = now();
            $reset->save();
            DB::table('sessions')->where('user_id', $owner->id)->delete();
            $this->audit->handle(AuditAction::AuthPasswordReset, 'Contraseña recuperada de la cuenta principal', $owner);

            return null;
        });

        if ($error !== null) {
            throw ValidationException::withMessages(['code' => $error]);
        }
    }
}
