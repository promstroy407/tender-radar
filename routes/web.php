<?php

declare(strict_types=1);

use App\Http\Controllers\ContractController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
