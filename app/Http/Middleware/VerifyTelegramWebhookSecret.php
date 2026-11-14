<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Telegram\TelegramConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает в webhook только запросы с секретом, заданным при setWebhook.
 */
class VerifyTelegramWebhookSecret
{
    public const HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    public function __construct(
        private readonly TelegramConfig $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expected = $this->config->webhookSecret;
        $given = $request->header(self::HEADER);

        if ($expected === null || ! is_string($given) || ! hash_equals($expected, $given)) {
            abort(403);
        }

        return $next($request);
    }
}
