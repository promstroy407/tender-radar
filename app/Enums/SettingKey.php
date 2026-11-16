<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case TelegramBotToken = 'telegram.bot_token';
    case TelegramApiUrl = 'telegram.api_url';
    case TelegramUpdatesMode = 'telegram.updates_mode';
    case EisToken = 'eis.token';
    case KpSubject = 'kp.subject';
    case KpBody = 'kp.body';
    case KpDailyLimit = 'kp.daily_limit';
    case NormativesStatus = 'normatives.status';
}
