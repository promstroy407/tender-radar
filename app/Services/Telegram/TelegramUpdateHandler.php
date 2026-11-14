<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Обработка входящих обновлений бота (из webhook или getUpdates).
 * Пока бот умеет одно: на /start присылает кнопку открытия Mini App.
 */
class TelegramUpdateHandler
{
    public function __construct(
        private readonly TelegramBotClient $bot,
        private readonly string $appUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! is_array($message)) {
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        if (! is_int($chatId) || ! is_string($text)) {
            return;
        }

        if (preg_match('#^/start(@\w+)?(\s|$)#', $text) === 1) {
            $this->sendOpenAppButton($chatId);
        }
    }

    private function sendOpenAppButton(int $chatId): void
    {
        $this->bot->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => 'Тендер-Радар: победители закупок по электромонтажу и слаботочке. Откройте приложение кнопкой ниже.',
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => 'Открыть Тендер-Радар', 'web_app' => ['url' => rtrim($this->appUrl, '/').'/']],
                ]],
            ],
        ]);
    }
}
