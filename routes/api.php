<?php

declare(strict_types=1);

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\PriceGapController;
use App\Http\Middleware\AuthenticateLeadsToken;
use Illuminate\Support\Facades\Route;

// Заявки с сайта es12.ru. Сначала ограничение частоты (30 в минуту), потом токен.
Route::post('leads', [LeadController::class, 'store'])
    ->middleware(['throttle:leads', AuthenticateLeadsToken::class])
    ->name('api.leads.store');

// Чему в прайсе нет цены — для ежедневного поиска рыночных цен. Только названия работ.
Route::get('price-gaps', [PriceGapController::class, 'index'])
    ->middleware('throttle:price-gaps')
    ->name('api.price-gaps');
