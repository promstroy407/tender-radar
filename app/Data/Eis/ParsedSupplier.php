<?php

declare(strict_types=1);

namespace App\Data\Eis;

/** Победитель (поставщик, подрядчик) по контракту. */
final readonly class ParsedSupplier
{
    public function __construct(
        public string $inn,
        public ?string $kpp,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $city,
    ) {}
}
