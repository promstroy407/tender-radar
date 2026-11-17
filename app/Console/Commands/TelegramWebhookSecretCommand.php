<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnvFileWriter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Создаёт TELEGRAM_WEBHOOK_SECRET прямо в .env, не показывая его.
 * Нужна, потому что в веб-консоли сервера неудобно набирать длинные строки.
 */
class TelegramWebhookSecretCommand extends Command
{
    private const KEY = 'TELEGRAM_WEBHOOK_SECRET';

    protected $signature = 'telegram:webhook-secret {--force : заменить уже заданный секрет}';

    protected $description = 'Telegram: создать секрет webhook в .env (значение не выводится)';

    public function handle(EnvFileWriter $env): int
    {
        try {
            if ($env->has(self::KEY) && ! $this->option('force')) {
                $this->line('Секрет уже задан. Заменить: --force (после этого нужно снова telegram:webhook set).');

                return self::SUCCESS;
            }

            $env->put(self::KEY, $env->randomSecret());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Секрет webhook записан в .env. Обновите кэш настроек: php artisan optimize');

        return self::SUCCESS;
    }
}
