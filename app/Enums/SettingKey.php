<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case TelegramBotToken = 'telegram.bot_token';
    case TelegramApiUrl = 'telegram.api_url';
    case TelegramUpdatesMode = 'telegram.updates_mode';
    // Чат владельца для уведомлений о заявках и одноразовый код привязки (/start <код>).
    case TelegramOwnerChatId = 'telegram.owner_chat_id';
    case TelegramLinkCode = 'telegram.link_code';
    case EisToken = 'eis.token';
    // Журнал сбора контрактов из ЕИС: последние запуски по регионам и дням (JSON).
    case EisImportLog = 'eis.import_log';
    case KpSubject = 'kp.subject';
    case KpBody = 'kp.body';
    case KpDailyLimit = 'kp.daily_limit';
    case NormativesStatus = 'normatives.status';
}
