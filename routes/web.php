<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthorizedPersonController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ForcedPasswordChangeController;
use App\Http\Controllers\IdPhotoController;
use App\Http\Controllers\OwnerEmailController;
use App\Http\Controllers\OwnerPasswordResetController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware('guest')->group(function () {
    Route::get('recuperar', [OwnerPasswordResetController::class, 'request'])->name('recovery.request');
    Route::post('recuperar', [OwnerPasswordResetController::class, 'send'])->middleware('throttle:recovery-send')->name('recovery.send');
    Route::get('recuperar/codigo', [OwnerPasswordResetController::class, 'showVerify'])->name('recovery.verify');
    Route::post('recuperar/codigo', [OwnerPasswordResetController::class, 'verify'])->middleware('throttle:recovery-verify')->name('recovery.verify.store');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::middleware('can:viewAny,'.User::class)->group(function () {
        Route::get('usuarios', [UserController::class, 'index'])->name('users.index');
        Route::post('usuarios', [UserController::class, 'store'])->name('users.store');
        Route::put('usuarios/{user}/contrasena', [UserController::class, 'setPassword'])->whereNumber('user')->name('users.password');
        Route::delete('usuarios/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
    });
    Route::middleware('can:change-own-email')->group(function () {
        Route::get('mi-cuenta/correo', [OwnerEmailController::class, 'edit'])->name('account.email');
        Route::post('mi-cuenta/correo', [OwnerEmailController::class, 'store'])->middleware('throttle:email-change-send')->name('account.email.store');
        Route::post('mi-cuenta/correo/confirmar', [OwnerEmailController::class, 'confirm'])->middleware('throttle:email-change-confirm')->name('account.email.confirm');
        Route::delete('mi-cuenta/correo', [OwnerEmailController::class, 'destroy'])->name('account.email.destroy');
    });
    Route::get('auditoria', [AuditLogController::class, 'index'])->middleware('can:view-audit')->name('audit.index');
    Route::get('media/{type}/{id}/{variant}', [IdPhotoController::class, 'show'])->whereNumber('id')->name('media.show');
    Route::get('/', fn () => to_route('clients.index'))->name('home');
    Route::get('clientes', [ClientController::class, 'index'])->name('clients.index');
    Route::post('clientes', [ClientController::class, 'store'])->name('clients.store');
    Route::post('clientes/buscar', [ClientController::class, 'search'])->middleware('throttle:30,1')->name('clients.search');
    Route::get('clientes/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
    Route::put('clientes/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
    Route::delete('clientes/{client}', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');
    Route::post('clientes/{client}/autorizados', [AuthorizedPersonController::class, 'store'])->whereNumber('client')->name('people.store');
    Route::put('autorizados/{person}', [AuthorizedPersonController::class, 'update'])->whereNumber('person')->name('people.update');
    Route::delete('autorizados/{person}', [AuthorizedPersonController::class, 'destroy'])->whereNumber('person')->name('people.destroy');
    Route::redirect('settings/password', '/cambiar-contrasena');
    Route::get('cambiar-contrasena', [ForcedPasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('cambiar-contrasena', [ForcedPasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
