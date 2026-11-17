<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnvFileWriter;
use App\Services\Telegram\OwnerChatService;
use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramConfigException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Создаёт LEADS_API_TOKEN — ключ, с которым сайт es12.ru присылает заявки на /api/leads.
 * Значение пишется в .env сервера и отправляется владельцу ботом в Telegram: из веб-консоли
 * сервера текст не скопировать. Если чат владельца не привязан — показывается в консоли.
 * В репозиторий и чат с Claude значение не попадает.
 */
class LeadsTokenCommand extends Command
{
    public const KEY = 'LEADS_API_TOKEN';

    protected $signature = 'leads:token {--force : заменить уже заданный токен (сайт перестанет отправлять заявки, пока не впишете новый)}';

    protected $description = 'Заявки с сайта: создать токен LEADS_API_TOKEN в .env и прислать его владельцу в Telegram';

    public function handle(EnvFileWriter $env, OwnerChatService $owner, Container $container): int
    {
        try {
            if ($env->has(self::KEY) && ! $this->option('force')) {
                $this->line('Токен уже задан. Показать его снова нельзя. Создать новый: leads:token --force');

                return self::SUCCESS;
            }

            $token = $env->randomSecret();
            $env->put(self::KEY, $token);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // На сервере настройки закэшированы (optimize при выкладке) — пересобираем кэш.
        // Он собирается в этом же процессе: новое значение должно победить старое
        // из окружения контейнера, иначе при замене токена в кэш попал бы прежний.
        if ($this->laravel->configurationIsCached()) {
            putenv(self::KEY.'='.$token);
            $_ENV[self::KEY] = $token;
            $_SERVER[self::KEY] = $token;
            $this->callSilently('config:cache');
        }

        $chatId = $owner->chatId();

        if ($chatId === null) {
            $this->showInConsole($token);

            return self::SUCCESS;
        }

        try {
            $bot = $container->make(TelegramBotClient::class);
            $bot->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => "Токен для заявок с сайта (LEADS_API_TOKEN) — следующим сообщением.\n"
                    ."Скопируйте его в .env сайта es12.ru, затем удалите оба сообщения.\n"
                    .'Никому не пересылайте и не вставляйте в чаты.',
            ]);
            $bot->call('sendMessage', ['chat_id' => $chatId, 'text' => $token]);
        } catch (TelegramApiException|TelegramConfigException $e) {
            $this->error('Токен записан в .env, но в Telegram не отправлен: '.$e->getMessage());
            $this->line('Запустите команду ещё раз с --force, когда бот будет на связи.');

            return self::FAILURE;
        }

        $this->line('Готово: токен записан в .env сервера и отправлен вам в Telegram.');

        return self::SUCCESS;
    }

    private function showInConsole(string $token): void
    {
        $this->newLine();
        $this->line('Чат для уведомлений не привязан — токен показывается здесь (один раз):');
        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->line('Впишите его в .env сайта es12.ru. Никому не пересылайте.');
    }
}
