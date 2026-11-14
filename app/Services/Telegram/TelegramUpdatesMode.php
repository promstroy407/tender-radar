<?php

declare(strict_types=1);

namespace App\Services\Telegram;

enum TelegramUpdatesMode: string
{
    /** Telegram сам присылает обновления на https://<домен>/telegram/webhook. */
    case Webhook = 'webhook';

    /** Запасной режим: сервер сам забирает обновления через getUpdates. */
    case Polling = 'polling';
}
