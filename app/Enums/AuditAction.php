<?php

namespace App\Enums;

enum AuditAction: string
{
    case ClientCreated = 'client.created';
    case ClientUpdated = 'client.updated';
    case ClientDeleted = 'client.deleted';
    case PersonCreated = 'person.created';
    case PersonUpdated = 'person.updated';
    case PersonDeleted = 'person.deleted';
    case UserCreated = 'user.created';
    case UserDeleted = 'user.deleted';
    case UserPasswordSetByOwner = 'user.password_set_by_owner';
    case UserEmailChangeRequested = 'user.email_change_requested';
    case UserEmailChangeFailed = 'user.email_change_failed';
    case UserEmailChanged = 'user.email_changed';
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthConfirmFailed = 'auth.confirm_failed';
    case AuthResetCodeSent = 'auth.reset_code_sent';
    case AuthResetCodeFailed = 'auth.reset_code_failed';
    case AuthPasswordReset = 'auth.password_reset';

    public function label(): string
    {
        return match ($this) {
            self::ClientCreated => 'Cliente creado',
            self::ClientUpdated => 'Cliente editado',
            self::ClientDeleted => 'Cliente eliminado',
            self::PersonCreated => 'Persona autorizada agregada',
            self::PersonUpdated => 'Persona autorizada editada',
            self::PersonDeleted => 'Persona autorizada eliminada',
            self::UserCreated => 'Usuario creado',
            self::UserDeleted => 'Usuario eliminado',
            self::UserPasswordSetByOwner => 'Contraseña asignada',
            self::UserEmailChangeRequested => 'Código de cambio de correo enviado',
            self::UserEmailChangeFailed => 'Código de cambio de correo incorrecto',
            self::UserEmailChanged => 'Correo cambiado',
            self::AuthLogin => 'Inicio de sesión',
            self::AuthLoginFailed => 'Inicio de sesión fallido',
            self::AuthConfirmFailed => 'Confirmación de contraseña fallida',
            self::AuthResetCodeSent => 'Código de recuperación enviado',
            self::AuthResetCodeFailed => 'Código de recuperación incorrecto',
            self::AuthPasswordReset => 'Contraseña recuperada',
        };
    }
}
