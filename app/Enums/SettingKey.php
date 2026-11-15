<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case TelegramBotToken = 'telegram.bot_token';
    case TelegramApiUrl = 'telegram.api_url';
}
