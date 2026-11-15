<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Установка и проверка webhook. Общая логика для консольной команды и страницы настроек.
 */
class TelegramWebhookService
{
    public function __construct(
        private readonly TelegramBotClient $bot,
        private readonly TelegramConfig $config,
        private readonly string $appUrl,
    ) {}

    public function webhookUrl(): string
    {
        return rtrim($this->appUrl, '/').'/telegram/webhook';
    }

    /** Возвращает адрес, на который Telegram будет присылать обновления. */
    public function set(): string
    {
        $url = $this->webhookUrl();

        $this->bot->call('setWebhook', [
            'url' => $url,
            'secret_token' => $this->config->requireWebhookSecret(),
            'allowed_updates' => ['message'],
        ]);

        return $url;
    }

    public function delete(): void
    {
        $this->bot->call('deleteWebhook');
    }

    /** Имя бота по getMe, без «@». */
    public function botUsername(): string
    {
        $result = $this->bot->call('getMe');

        return is_array($result) && is_string($result['username'] ?? null) ? $result['username'] : '?';
    }

    /**
     * @return array{url: string, pending: int, last_error: string|null}
     */
    public function info(): array
    {
        $result = $this->bot->call('getWebhookInfo');
        $info = is_array($result) ? $result : [];

        return [
            'url' => is_string($info['url'] ?? null) ? $info['url'] : '',
            'pending' => is_int($info['pending_update_count'] ?? null) ? $info['pending_update_count'] : 0,
            'last_error' => is_string($info['last_error_message'] ?? null) ? $this->bot->mask($info['last_error_message']) : null,
        ];
    }
}
