<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use RuntimeException;

/** Неверные или неполные настройки Telegram в .env. */
class TelegramConfigException extends RuntimeException {}
