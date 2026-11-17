<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\SettingKey;
use App\Services\Eis\EisConfig;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramConfig;
use App\Services\Telegram\TelegramUpdateHandler;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Настройки Telegram проверяются при каждом обращении: неверный адрес
        // пересыльщика даёт понятную ошибку, а не молчаливый сбой.
        $this->app->bind(TelegramConfig::class, function (Application $app): TelegramConfig {
            /** @var array<string, mixed> $settings */
            $settings = (array) $app->make(Config::class)->get('services.telegram', []);

            // Значения из «Настройки → Telegram» (зашифрованы в базе) важнее .env.
            $overrides = $app->make(SettingsService::class);
            $settings['bot_token'] = $overrides->get(SettingKey::TelegramBotToken) ?? ($settings['bot_token'] ?? null);
            $settings['api_url'] = $overrides->get(SettingKey::TelegramApiUrl) ?? ($settings['api_url'] ?? null);
            $settings['updates_mode'] = $overrides->get(SettingKey::TelegramUpdatesMode) ?? ($settings['updates_mode'] ?? null);

            return TelegramConfig::fromArray($settings);
        });

        $this->app->bind(EisConfig::class, function (Application $app): EisConfig {
            $config = $app->make(Config::class);

            return new EisConfig(
                token: $app->make(SettingsService::class)->get(SettingKey::EisToken)
                    ?? (is_string($config->get('services.eis.token')) ? $config->get('services.eis.token') : null),
                url44: (string) $config->get('services.eis.url_44'),
            );
        });

        $this->app->when([TelegramUpdateHandler::class, TelegramWebhookService::class])
            ->needs('$appUrl')
            ->giveConfig('app.url');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Заявки с сайта: не больше 30 запросов в минуту, дальше — 429.
        RateLimiter::for('leads', fn (Request $request): Limit => Limit::perMinute(30)->by('leads'));
    }
}
