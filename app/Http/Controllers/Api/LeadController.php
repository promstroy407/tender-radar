<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadRequest;
use App\Jobs\NotifyNewLead;
use App\Services\DealService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;

/**
 * Приём заявок с сайта es12.ru: каждая заявка — новая сделка «Сайт / Новая»
 * и уведомление владельцу в Telegram.
 */
class LeadController extends Controller
{
    public function __construct(
        private readonly DealService $deals,
        private readonly Dispatcher $queue,
    ) {}

    public function store(LeadRequest $request): JsonResponse
    {
        [$deal, $created] = $this->deals->createFromLead($request->toLead());

        if ($created) {
            $this->queue->dispatch(new NotifyNewLead($deal->id));
        }

        // 201 — создана; 200 — такой external_id уже был, ничего не дублируем.
        return new JsonResponse(['deal_id' => $deal->id], $created ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);
    }
}
