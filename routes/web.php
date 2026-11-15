<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EisSettingsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('settings/eis')->name('settings.eis')
        ->middleware('can:update,'.Setting::class)
        ->controller(EisSettingsController::class)
        ->group(function (): void {
            Route::get('/', 'edit');
            Route::put('/', 'update')->name('.update');
            Route::post('probe', 'probe')->name('.probe');
        });

    Route::prefix('settings/telegram')->name('settings.telegram')
        ->middleware('can:update,'.Setting::class)
        ->controller(TelegramSettingsController::class)
        ->group(function (): void {
            Route::get('/', 'edit');
            Route::put('/', 'update')->name('.update');
            Route::post('check', 'check')->name('.check');
            Route::post('webhook', 'setWebhook')->name('.webhook');
        });
});

// JSON API для Mini App и браузерной версии. Доступ только после входа.
Route::prefix('api')->middleware('auth')->name('api.')->group(function (): void {
    Route::get('contracts', [ContractController::class, 'index'])
        ->name('contracts.index');

    Route::get('contracts/{contract}', [ContractController::class, 'show'])
        ->can('view', 'contract')
        ->name('contracts.show');
});

// Входящие обновления бота. Защита — секрет из setWebhook в заголовке запроса.
Route::post('telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhookSecret::class)
    ->name('telegram.webhook');
