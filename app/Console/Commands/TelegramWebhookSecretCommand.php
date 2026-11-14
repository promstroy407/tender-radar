<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;

/**
 * Создаёт TELEGRAM_WEBHOOK_SECRET прямо в .env, не показывая его.
 * Нужна, потому что в веб-консоли сервера неудобно набирать длинные строки.
 */
class TelegramWebhookSecretCommand extends Command
{
    private const KEY = 'TELEGRAM_WEBHOOK_SECRET';

    protected $signature = 'telegram:webhook-secret {--force : заменить уже заданный секрет}';

    protected $description = 'Telegram: создать секрет webhook в .env (значение не выводится)';

    public function handle(Application $app): int
    {
        $path = $app->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            $this->error('Файл .env не найден или недоступен для записи: '.$path);

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $pattern = '/^'.self::KEY.'=(.*)$/m';
        $hasValue = preg_match($pattern, $contents, $match) === 1 && trim($match[1]) !== '';

        if ($hasValue && ! $this->option('force')) {
            $this->line('Секрет уже задан. Заменить: --force (после этого нужно снова telegram:webhook set).');

            return self::SUCCESS;
        }

        $line = self::KEY.'='.bin2hex(random_bytes(32));

        $contents = preg_match($pattern, $contents) === 1
            ? (string) preg_replace($pattern, $line, $contents, 1)
            : rtrim($contents, "\n")."\n".$line."\n";

        file_put_contents($path, $contents);

        $this->line('Секрет webhook записан в .env. Обновите кэш настроек: php artisan optimize');

        return self::SUCCESS;
    }
}
