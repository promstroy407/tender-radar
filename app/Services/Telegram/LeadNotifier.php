<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Models\Deal;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Str;

/**
 * Сообщение владельцу в Telegram о новой заявке (через пересыльщик, как все запросы бота).
 */
class LeadNotifier
{
    private const MESSAGE_LIMIT = 500;

    public function __construct(
        private readonly TelegramBotClient $bot,
        private readonly OwnerChatService $owner,
        private readonly UrlGenerator $url,
        private readonly string $appUrl,
    ) {}

    /** false — чат владельца не привязан, сообщение не отправлялось. */
    public function notify(Deal $deal): bool
    {
        $chatId = $this->owner->chatId();

        if ($chatId === null) {
            return false;
        }

        $this->bot->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $this->text($deal),
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => [
                'inline_keyboard' => [[
                    // Адрес от APP_URL: в очереди нет запроса, по которому его можно было бы узнать.
                    ['text' => 'Открыть сделку', 'url' => rtrim($this->appUrl, '/').$this->url->route('deals.show', $deal, false)],
                ]],
            ],
        ]);

        return true;
    }

    public function text(Deal $deal): string
    {
        $lines = ['Новая заявка с сайта'.($deal->external_id !== null ? ' ('.$deal->external_id.')' : '')];
        $lines[] = implode(', ', array_filter([$deal->name, $deal->phone]));

        $fields = [
            'Организация' => $deal->organization,
            'Почта' => $deal->email,
            'Объект' => $deal->facility,
            'Услуга' => $deal->service,
            'Калькулятор' => $deal->estimate,
            'Откуда узнали' => $deal->origin,
        ];
        foreach ($fields as $label => $value) {
            if ($value !== null) {
                $lines[] = $label.': '.$value;
            }
        }

        if ($deal->message !== null) {
            $lines[] = '';
            $lines[] = Str::limit($deal->message, self::MESSAGE_LIMIT);
        }

        return implode("\n", $lines);
    }
}
