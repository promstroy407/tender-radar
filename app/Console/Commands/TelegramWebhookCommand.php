<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramConfig;
use App\Services\Telegram\TelegramConfigException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Управление ботом: проверка связи, установка и снятие webhook.
 */
class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook {action=info : me | info | set | delete}';

    protected $description = 'Telegram: проверка бота (me), состояние webhook (info), установка (set), снятие (delete)';

    public function handle(TelegramBotClient $bot, TelegramConfig $telegram, Config $config): int
    {
        try {
            return match ($this->argument('action')) {
                'me' => $this->me($bot),
                'info' => $this->info($bot),
                'set' => $this->set($bot, $telegram, $config),
                'delete' => $this->delete($bot),
                default => $this->unknownAction(),
            };
        } catch (TelegramApiException|TelegramConfigException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function me(TelegramBotClient $bot): int
    {
        $result = $bot->call('getMe');
        $username = is_array($result) && is_string($result['username'] ?? null) ? $result['username'] : '?';
        $this->line('Бот на связи: @'.$username);

        return self::SUCCESS;
    }

    private function info(TelegramBotClient $bot): int
    {
        $result = $bot->call('getWebhookInfo');
        $info = is_array($result) ? $result : [];

        $this->table(['Поле', 'Значение'], [
            ['url', (string) ($info['url'] ?? '')],
            ['ожидают доставки', (string) ($info['pending_update_count'] ?? 0)],
            ['последняя ошибка', (string) ($info['last_error_message'] ?? '—')],
        ]);

        return self::SUCCESS;
    }

    private function set(TelegramBotClient $bot, TelegramConfig $telegram, Config $config): int
    {
        $url = rtrim((string) $config->get('app.url'), '/').'/telegram/webhook';

        $bot->call('setWebhook', [
            'url' => $url,
            'secret_token' => $telegram->requireWebhookSecret(),
            'allowed_updates' => ['message'],
        ]);

        $this->line('Webhook установлен: '.$url);
        $this->line('Проверьте доставку: php artisan telegram:webhook info');

        return self::SUCCESS;
    }

    private function delete(TelegramBotClient $bot): int
    {
        $bot->call('deleteWebhook');
        $this->line('Webhook снят.');

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->error('Действие: me, info, set или delete.');

        return self::INVALID;
    }
}
