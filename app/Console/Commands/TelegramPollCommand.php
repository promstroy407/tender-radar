<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramConfig;
use App\Services\Telegram\TelegramUpdateHandler;
use App\Services\Telegram\TelegramUpdatesMode;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Запасной режим приёма сообщений: getUpdates (long polling) через пересыльщик.
 * Работает, только если TELEGRAM_UPDATES_MODE=polling. Планировщик запускает его раз в минуту.
 */
class TelegramPollCommand extends Command
{
    private const OFFSET_KEY = 'telegram:updates-offset';

    private const LONG_POLL_SECONDS = 25;

    protected $signature = 'telegram:poll';

    protected $description = 'Telegram: забрать новые сообщения через getUpdates (режим polling)';

    public function handle(
        TelegramConfig $config,
        TelegramBotClient $bot,
        TelegramUpdateHandler $handler,
        Cache $cache,
    ): int {
        if ($config->updatesMode !== TelegramUpdatesMode::Polling) {
            $this->line('Режим webhook: getUpdates не нужен.');

            return self::SUCCESS;
        }

        try {
            // Webhook и getUpdates одновременно не работают.
            $bot->call('deleteWebhook');

            $offset = (int) $cache->get(self::OFFSET_KEY, 0);
            $updates = $bot->call('getUpdates', [
                'offset' => $offset,
                'timeout' => self::LONG_POLL_SECONDS,
                'allowed_updates' => ['message'],
            ], self::LONG_POLL_SECONDS + 10);
        } catch (TelegramApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach (is_array($updates) ? $updates : [] as $update) {
            if (! is_array($update) || ! is_int($update['update_id'] ?? null)) {
                continue;
            }

            try {
                /** @var array<string, mixed> $update */
                $handler->handle($update);
            } catch (TelegramApiException $e) {
                $this->warn($e->getMessage());
            }

            $cache->forever(self::OFFSET_KEY, $update['update_id'] + 1);
        }

        return self::SUCCESS;
    }
}
