<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramUpdateHandler $handler,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $update */
        $update = $request->json()->all();

        try {
            $this->handler->handle($update);
        } catch (TelegramApiException $e) {
            // Отвечаем 200, иначе Telegram будет бесконечно повторять доставку.
            $this->logger->warning($e->getMessage());
        }

        return new JsonResponse(['ok' => true]);
    }
}
