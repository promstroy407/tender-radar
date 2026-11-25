<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Estimates\PriceGapReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Открытый список того, чему в прайсе нет цены, — для ежедневного поиска рыночных
 * цен (решение заказчика 25.11.2026). Только названия работ и единицы.
 */
class PriceGapController extends Controller
{
    public function __construct(
        private readonly PriceGapReport $report,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['generated_at' => Carbon::now()->toIso8601String()] + $this->report->build(), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
