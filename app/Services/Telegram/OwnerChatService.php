<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\SettingKey;
use App\Services\SettingsService;

/**
 * Чат владельца, куда бот шлёт уведомления. Привязка — по одноразовой ссылке
 * t.me/<бот>?start=<код>: владелец открывает её, Telegram присылает /start <код>.
 */
class OwnerChatService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function chatId(): ?int
    {
        $value = $this->settings->get(SettingKey::TelegramOwnerChatId);

        return $value !== null && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }

    /** Новый одноразовый код; прежний перестаёт действовать. */
    public function newLinkCode(): string
    {
        $code = bin2hex(random_bytes(12));
        $this->settings->put(SettingKey::TelegramLinkCode, $code);

        return $code;
    }

    public function linkUrl(string $botUsername, string $code): string
    {
        return 'https://t.me/'.rawurlencode($botUsername).'?start='.$code;
    }

    /** Код совпал — запоминаем чат и гасим код. */
    public function link(string $code, int $chatId): bool
    {
        $expected = $this->settings->get(SettingKey::TelegramLinkCode);

        if ($expected === null || ! hash_equals($expected, $code)) {
            return false;
        }

        $this->settings->put(SettingKey::TelegramOwnerChatId, (string) $chatId);
        $this->settings->forget(SettingKey::TelegramLinkCode);

        return true;
    }

    public function unlink(): void
    {
        $this->settings->forget(SettingKey::TelegramOwnerChatId);
        $this->settings->forget(SettingKey::TelegramLinkCode);
    }
}
