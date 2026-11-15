<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ссылки, открывающие чат с победителем в мессенджере. Сообщение отправляет
 * сам пользователь — так оно уходит с его номера, а не от бота.
 */
class MessengerLinkBuilder
{
    /** Номер в формате 7XXXXXXXXXX или null, если это не российский мобильный/городской. */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        return strlen($digits) === 11 && $digits[0] === '7' ? $digits : null;
    }

    public function whatsapp(?string $phone, string $text): ?string
    {
        $number = $this->normalizePhone($phone);

        return $number === null ? null : 'https://wa.me/'.$number.'?'.http_build_query(['text' => $text], '', '&', PHP_QUERY_RFC3986);
    }

    /** Telegram открывает чат по номеру, если человек разрешил находить себя по номеру. Текст вставляется из буфера. */
    public function telegram(?string $phone): ?string
    {
        $number = $this->normalizePhone($phone);

        return $number === null ? null : 'https://t.me/+'.$number;
    }
}
