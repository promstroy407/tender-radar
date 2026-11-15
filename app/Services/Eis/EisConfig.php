<?php

declare(strict_types=1);

namespace App\Services\Eis;

/**
 * Настройки доступа к сервису отдачи информации ЕИС (getDocsIP).
 */
final readonly class EisConfig
{
    public function __construct(
        public ?string $token,
        public string $url44,
    ) {}

    public function requireToken(): string
    {
        if ($this->token === null || $this->token === '') {
            throw new EisException('Не задан токен ЕИС: вставьте его в «Настройки → ЕИС».');
        }

        return $this->token;
    }
}
