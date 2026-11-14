<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Проверенные настройки Telegram. Создаётся из config('services.telegram').
 */
final readonly class TelegramConfig
{
    public const DEFAULT_API_URL = 'https://api.telegram.org';

    public function __construct(
        public ?string $botToken,
        public string $apiUrl,
        public ?string $webhookSecret,
        public TelegramUpdatesMode $updatesMode,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $mode = TelegramUpdatesMode::tryFrom((string) ($config['updates_mode'] ?? 'webhook'));

        if ($mode === null) {
            throw new TelegramConfigException(
                'TELEGRAM_UPDATES_MODE должен быть «webhook» или «polling».',
            );
        }

        return new self(
            botToken: self::nullIfBlank($config['bot_token'] ?? null),
            apiUrl: self::normalizeApiUrl(self::nullIfBlank($config['api_url'] ?? null)),
            webhookSecret: self::nullIfBlank($config['webhook_secret'] ?? null),
            updatesMode: $mode,
        );
    }

    public function requireToken(): string
    {
        if ($this->botToken === null) {
            throw new TelegramConfigException('Не задан TELEGRAM_BOT_TOKEN в .env.');
        }

        return $this->botToken;
    }

    public function requireWebhookSecret(): string
    {
        if ($this->webhookSecret === null) {
            throw new TelegramConfigException('Не задан TELEGRAM_WEBHOOK_SECRET в .env.');
        }

        return $this->webhookSecret;
    }

    private static function normalizeApiUrl(?string $url): string
    {
        if ($url === null) {
            return self::DEFAULT_API_URL;
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || ($parts['host'] ?? '') === '') {
            throw new TelegramConfigException(
                'TELEGRAM_API_URL должен быть адресом вида https://хост/путь (только https).',
            );
        }

        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])) {
            throw new TelegramConfigException(
                'TELEGRAM_API_URL не должен содержать «?», «#» или логин в адресе.',
            );
        }

        return rtrim($url, '/');
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
