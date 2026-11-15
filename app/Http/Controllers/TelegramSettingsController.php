<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SettingKey;
use App\Http\Requests\TelegramSettingsRequest;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramConfigException;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * «Настройки → Telegram»: токен бота и адрес пересыльщика вставляются здесь,
 * хранятся зашифрованными и никогда не показываются обратно.
 */
class TelegramSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function edit(): View
    {
        return view('settings.telegram', [
            'tokenSource' => $this->source(SettingKey::TelegramBotToken, 'services.telegram.bot_token'),
            'apiUrlSource' => $this->source(SettingKey::TelegramApiUrl, 'services.telegram.api_url'),
            'webhookSecretSet' => filled($this->config->get('services.telegram.webhook_secret')),
        ]);
    }

    public function update(TelegramSettingsRequest $request): RedirectResponse
    {
        $this->apply(SettingKey::TelegramBotToken, $request->botToken(), $request->boolean('clear_bot_token'));
        $this->apply(SettingKey::TelegramApiUrl, $request->apiUrl(), $request->boolean('clear_api_url'));

        return redirect()->route('settings.telegram')->with('status', 'Настройки сохранены.');
    }

    public function check(): RedirectResponse
    {
        return $this->withWebhookService(
            fn (TelegramWebhookService $webhook): string => 'Бот на связи: @'.$webhook->botUsername(),
        );
    }

    public function setWebhook(): RedirectResponse
    {
        return $this->withWebhookService(function (TelegramWebhookService $webhook): string {
            $url = $webhook->set();
            $info = $webhook->info();

            return 'Webhook установлен: '.$url.'. Ожидают доставки: '.$info['pending']
                .'. Последняя ошибка: '.($info['last_error'] ?? 'нет').'.';
        });
    }

    private function apply(SettingKey $key, ?string $value, bool $clear): void
    {
        if ($value !== null) {
            $this->settings->put($key, $value);
        } elseif ($clear) {
            $this->settings->forget($key);
        }
    }

    /** Откуда берётся значение: «settings», «env» или null (не задано). */
    private function source(SettingKey $key, string $configKey): ?string
    {
        if ($this->settings->has($key)) {
            return 'settings';
        }

        return filled($this->config->get($configKey)) ? 'env' : null;
    }

    /**
     * @param  callable(TelegramWebhookService): string  $action
     */
    private function withWebhookService(callable $action): RedirectResponse
    {
        try {
            // Сервис создаётся здесь, чтобы ошибка настроек стала сообщением, а не ошибкой 500.
            $message = $action($this->container->make(TelegramWebhookService::class));

            return redirect()->route('settings.telegram')->with('status', $message);
        } catch (TelegramApiException|TelegramConfigException $e) {
            return redirect()->route('settings.telegram')->with('error', $e->getMessage());
        }
    }
}
