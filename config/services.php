<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Telegram Bot API. В московском дата-центре api.telegram.org недоступен,
    // поэтому запросы идут через пересыльщик: TELEGRAM_API_URL — его адрес
    // с секретным путём. Пусто — обычный https://api.telegram.org.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'api_url' => env('TELEGRAM_API_URL'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        // webhook — Telegram сам присылает обновления; polling — запасной режим getUpdates.
        'updates_mode' => env('TELEGRAM_UPDATES_MODE', 'webhook'),
    ],

    // ЕИС (zakupki.gov.ru), сервис отдачи информации getDocsIP. Токен получателя данных
    // выдаётся на zakupki.gov.ru/pmd через Госуслуги; задаётся на странице «Настройки → ЕИС».
    'eis' => [
        'token' => env('EIS_TOKEN'),
        'url_44' => env('EIS_URL_44', 'https://int44.zakupki.gov.ru/eis-integration/services/getDocsIP'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
