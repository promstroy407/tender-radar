<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use RuntimeException;

/**
 * Ошибка обращения к Bot API. Текст уже очищен от токена и адреса пересыльщика,
 * исходное исключение намеренно не сохраняется: в нём полный URL с секретами.
 */
class TelegramApiException extends RuntimeException {}
