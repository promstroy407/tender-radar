<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramConfigException;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Console\Command;

/**
 * Управление ботом: проверка связи, установка и снятие webhook.
 */
class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook {action=info : me | info | set | delete}';

    protected $description = 'Telegram: проверка бота (me), состояние webhook (info), установка (set), снятие (delete)';

    public function handle(TelegramWebhookService $webhook): int
    {
        try {
            return match ($this->argument('action')) {
                'me' => $this->showMe($webhook),
                'info' => $this->showWebhookInfo($webhook),
                'set' => $this->setWebhook($webhook),
                'delete' => $this->deleteWebhook($webhook),
                default => $this->unknownAction(),
            };
        } catch (TelegramApiException|TelegramConfigException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function showMe(TelegramWebhookService $webhook): int
    {
        $this->line('Бот на связи: @'.$webhook->botUsername());

        return self::SUCCESS;
    }

    private function showWebhookInfo(TelegramWebhookService $webhook): int
    {
        $info = $webhook->info();

        $this->table(['Поле', 'Значение'], [
            ['url', $info['url']],
            ['ожидают доставки', (string) $info['pending']],
            ['последняя ошибка', $info['last_error'] ?? '—'],
        ]);

        return self::SUCCESS;
    }

    private function setWebhook(TelegramWebhookService $webhook): int
    {
        $this->line('Webhook установлен: '.$webhook->set());
        $this->line('Проверьте доставку: php artisan telegram:webhook info');

        return self::SUCCESS;
    }

    private function deleteWebhook(TelegramWebhookService $webhook): int
    {
        $webhook->delete();
        $this->line('Webhook снят.');

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->error('Действие: me, info, set или delete.');

        return self::INVALID;
    }
}
