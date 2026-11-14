<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Telegram\TelegramConfig;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
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

            return TelegramConfig::fromArray($settings);
        });

        $this->app->when(TelegramUpdateHandler::class)
            ->needs('$appUrl')
            ->giveConfig('app.url');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
