<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

// Todas las rutas de la API usan sesión + protección CSRF (grupo "web"):
// el navegador guarda una cookie HttpOnly y nunca ve un token de acceso.
Route::middleware('web')->group(function () {
    // Entrega la cookie XSRF-TOKEN antes de iniciar sesión.
    Route::get('csrf', fn () => response()->noContent());

    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth', 'throttle:120,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);

        Route::put('account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1');
        Route::get('account/activity', [AccountController::class, 'activity']);

        Route::apiResource('clients', ClientController::class);
        Route::apiResource('clients.projects', ProjectController::class)->shallow()->only(['store', 'update', 'destroy']);
    });
});
