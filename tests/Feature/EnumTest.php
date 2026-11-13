<?php

use App\Enums\AuditAction;
use App\Enums\Relationship;

it('exposes the nineteen relationships in the specified order with Spanish labels', function () {
    expect(Relationship::options())->toBe([
        ['value' => 'hijo', 'label' => 'Hijo'], ['value' => 'hija', 'label' => 'Hija'],
        ['value' => 'padre', 'label' => 'Padre'], ['value' => 'madre', 'label' => 'Madre'],
        ['value' => 'esposo', 'label' => 'Esposo'], ['value' => 'esposa', 'label' => 'Esposa'],
        ['value' => 'hermano', 'label' => 'Hermano'], ['value' => 'hermana', 'label' => 'Hermana'],
        ['value' => 'abuelo', 'label' => 'Abuelo'], ['value' => 'abuela', 'label' => 'Abuela'],
        ['value' => 'nieto', 'label' => 'Nieto'], ['value' => 'nieta', 'label' => 'Nieta'],
        ['value' => 'tio', 'label' => 'Tío'], ['value' => 'tia', 'label' => 'Tía'],
        ['value' => 'sobrino', 'label' => 'Sobrino'], ['value' => 'sobrina', 'label' => 'Sobrina'],
        ['value' => 'primo', 'label' => 'Primo'], ['value' => 'prima', 'label' => 'Prima'],
        ['value' => 'otro', 'label' => 'Otro'],
    ]);
});

it('labels every specified audit event in Spanish', function () {
    $labels = [];
    foreach (AuditAction::cases() as $action) {
        $labels[$action->value] = $action->label();
    }

    expect($labels)->toBe([
        'client.created' => 'Cliente creado', 'client.updated' => 'Cliente editado', 'client.deleted' => 'Cliente eliminado',
        'person.created' => 'Persona autorizada agregada', 'person.updated' => 'Persona autorizada editada', 'person.deleted' => 'Persona autorizada eliminada',
        'user.created' => 'Usuario creado', 'user.deleted' => 'Usuario eliminado',
        'user.password_set_by_owner' => 'Contraseña asignada',
        'user.email_change_requested' => 'Código de cambio de correo enviado', 'user.email_change_failed' => 'Código de cambio de correo incorrecto',
        'user.email_changed' => 'Correo cambiado', 'auth.login' => 'Inicio de sesión',
        'auth.login_failed' => 'Inicio de sesión fallido', 'auth.confirm_failed' => 'Confirmación de contraseña fallida',
        'auth.reset_code_sent' => 'Código de recuperación enviado', 'auth.reset_code_failed' => 'Código de recuperación incorrecto',
        'auth.password_reset' => 'Contraseña recuperada',
    ]);
});
