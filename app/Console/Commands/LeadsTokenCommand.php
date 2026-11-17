<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EnvFileWriter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Создаёт LEADS_API_TOKEN — ключ, с которым сайт es12.ru присылает заявки на /api/leads.
 * Значение пишется в .env сервера и один раз показывается в терминале, чтобы владелец
 * сам вписал его в .env сайта. В чат и в репозиторий оно не попадает.
 */
class LeadsTokenCommand extends Command
{
    public const KEY = 'LEADS_API_TOKEN';

    protected $signature = 'leads:token {--force : заменить уже заданный токен (сайт перестанет отправлять заявки, пока не впишете новый)}';

    protected $description = 'Заявки с сайта: создать токен LEADS_API_TOKEN в .env и показать его один раз';

    public function handle(EnvFileWriter $env): int
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

        $this->newLine();
        $this->line('Токен для заявок с сайта (показывается один раз):');
        $this->newLine();
        $this->line($token);
        $this->newLine();
        $this->line('1. Скопируйте его и впишите в .env сайта es12.ru — тот же самый.');
        $this->line('2. Никому не пересылайте: в чат и в файлы проекта его не вставляйте.');
        $this->line('На сервере Тендер-Радара он уже записан в .env, настройки обновлены.');

        return self::SUCCESS;
    }
}
