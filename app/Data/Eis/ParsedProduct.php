<?php

declare(strict_types=1);

namespace App\Data\Eis;

/** Позиция контракта: код ОКПД2 и наименование. */
final readonly class ParsedProduct
{
    public function __construct(
        public ?string $okpd2,
        public string $name,
    ) {}
}
