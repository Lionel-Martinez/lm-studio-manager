<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\GeographyOverviewController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceOverviewController;
use Illuminate\Support\Facades\Route;

// Todas las rutas de la API usan sesión + protección CSRF (grupo "web"):
// el navegador guarda una cookie HttpOnly y nunca ve un token de acceso.
Route::middleware('web')->group(function () {
    // Entrega la cookie XSRF-TOKEN antes de iniciar sesión.
    Route::get('csrf', fn () => response()->noContent());

    Route::post('login', [AuthController::class, 'login']);
    Route::post('login/two-factor', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:20,1');

    Route::middleware(['auth', 'throttle:120,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);

        Route::put('account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1');
        Route::middleware('throttle:10,1')->prefix('account/two-factor')->group(function () {
            Route::post('setup', [TwoFactorController::class, 'setup']);
            Route::post('confirm', [TwoFactorController::class, 'confirm']);
            Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
            Route::delete('/', [TwoFactorController::class, 'disable']);
        });
        Route::get('account/activity', [AccountController::class, 'activity']);

        Route::post('clients/{client}/deactivate', [ClientController::class, 'deactivate']);
        Route::post('clients/{client}/reactivate', [ClientController::class, 'reactivate']);
        Route::apiResource('clients', ClientController::class);
        Route::prefix('export')->middleware('throttle:20,1')->group(function () {
            Route::get('clients', [ExportController::class, 'clients']);
            Route::get('projects', [ExportController::class, 'projects']);
            Route::get('payments', [ExportController::class, 'payments']);
        });
        Route::get('geo/countries', [GeoController::class, 'countries']);
        Route::get('geo/regions', [GeoController::class, 'regions']);
        Route::get('dashboard', DashboardController::class);
        Route::get('reports', ReportController::class);
        Route::get('services', ServiceOverviewController::class);
        Route::get('geography', GeographyOverviewController::class);
        Route::get('agenda', [AgendaController::class, 'index']);
        Route::get('agenda/summary', [AgendaController::class, 'summary']);
        Route::apiResource('reminders', ReminderController::class)->only(['store', 'update', 'destroy']);
        Route::get('payments/summary', [PaymentController::class, 'summary']);
        Route::apiResource('notes', NoteController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('payments/{payment}/receipt', [ReceiptController::class, 'store']);
        Route::get('receipts/{receipt}', [ReceiptController::class, 'show']);
        Route::post('receipts/{receipt}/void', [ReceiptController::class, 'void']);
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('clients.projects', ProjectController::class)->shallow()->only(['store', 'update', 'destroy']);
    });
});
