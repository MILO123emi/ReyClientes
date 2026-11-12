<?php

use App\Http\Controllers\ForcedPasswordChangeController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => to_route('dashboard'))->name('home');
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::redirect('settings/password', '/cambiar-contrasena');
    Route::get('cambiar-contrasena', [ForcedPasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('cambiar-contrasena', [ForcedPasswordChangeController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.change.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
