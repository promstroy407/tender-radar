<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EisSettingsController;
use App\Http\Controllers\KpTemplateController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\SearchSettingsController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\TelegramSettingsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WorkController;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use App\Models\Offer;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Экраны по макету: Тендеры → Карточка → В работе, Шаблон КП, Настройки.
    Route::get('/', [TenderController::class, 'index'])->name('home');
    Route::get('tenders/{contract}', [TenderController::class, 'show'])
        ->can('view', 'contract')
        ->name('tenders.show');

    Route::prefix('tenders/{contract}')->name('offers.')
        ->middleware(['can:view,contract', 'can:create,'.Offer::class])
        ->controller(OfferController::class)
        ->group(function (): void {
            Route::post('email', 'email')->name('email');
            Route::post('messenger', 'messenger')->name('messenger');
            Route::post('dismiss', 'dismiss')->name('dismiss');
        });

    Route::get('work', [WorkController::class, 'index'])->name('work');
    Route::put('offers/{offer}', [WorkController::class, 'update'])->name('offers.update');

    Route::middleware('can:update,'.Setting::class)->group(function (): void {
        Route::get('template', [KpTemplateController::class, 'edit'])->name('template');
        Route::put('template', [KpTemplateController::class, 'update'])->name('template.update');

        Route::get('settings', [SearchSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [SearchSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/demo', [SearchSettingsController::class, 'loadDemo'])->name('settings.demo');
        Route::delete('settings/demo', [SearchSettingsController::class, 'removeDemo'])->name('settings.demo.remove');
    });

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
