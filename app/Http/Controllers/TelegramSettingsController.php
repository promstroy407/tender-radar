<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SettingKey;
use App\Http\Requests\TelegramSettingsRequest;
use App\Services\SettingsService;
use App\Services\Telegram\OwnerChatService;
use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramConfigException;
use App\Services\Telegram\TelegramUpdatesMode;
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
        private readonly OwnerChatService $owner,
    ) {}

    public function edit(): View
    {
        return view('settings.telegram', [
            'tokenSource' => $this->settings->source(SettingKey::TelegramBotToken, 'services.telegram.bot_token'),
            'apiUrlSource' => $this->settings->source(SettingKey::TelegramApiUrl, 'services.telegram.api_url'),
            'webhookSecretSet' => filled($this->config->get('services.telegram.webhook_secret')),
            'updatesMode' => $this->settings->get(SettingKey::TelegramUpdatesMode)
                ?? (string) $this->config->get('services.telegram.updates_mode', TelegramUpdatesMode::Webhook->value),
            'ownerLinked' => $this->owner->chatId() !== null,
        ]);
    }

    public function update(TelegramSettingsRequest $request): RedirectResponse
    {
        $this->settings->applyInput(SettingKey::TelegramBotToken, $request->botToken(), $request->boolean('clear_bot_token'));
        $this->settings->applyInput(SettingKey::TelegramApiUrl, $request->apiUrl(), $request->boolean('clear_api_url'));

        if ($request->updatesMode() !== null) {
            $this->settings->put(SettingKey::TelegramUpdatesMode, $request->updatesMode()->value);
        }

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
            if ($webhook->mode() === TelegramUpdatesMode::Polling) {
                $webhook->delete();

                return 'Режим опроса: webhook снят, сервер сам забирает сообщения через пересыльщик раз в минуту.';
            }

            $url = $webhook->set();
            $info = $webhook->info();

            return 'Webhook установлен: '.$url.'. Ожидают доставки: '.$info['pending']
                .'. Последняя ошибка: '.($info['last_error'] ?? 'нет').'.';
        });
    }

    /** Одноразовая ссылка: владелец открывает её в Telegram — чат привязывается к уведомлениям. */
    public function linkOwner(): RedirectResponse
    {
        try {
            $username = $this->container->make(TelegramWebhookService::class)->botUsername();
        } catch (TelegramApiException|TelegramConfigException $e) {
            return redirect()->route('settings.telegram')->with('error', $e->getMessage());
        }

        return redirect()->route('settings.telegram')
            ->with('status', 'Откройте ссылку на телефоне и нажмите «Запустить» (Start) в чате с ботом.')
            ->with('owner_link', $this->owner->linkUrl($username, $this->owner->newLinkCode()));
    }

    public function unlinkOwner(): RedirectResponse
    {
        $this->owner->unlink();

        return redirect()->route('settings.telegram')->with('status', 'Уведомления о заявках отключены.');
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
