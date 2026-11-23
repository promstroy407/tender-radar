<?php

declare(strict_types=1);

namespace App\Data\Eis;

use Illuminate\Support\Carbon;

/**
 * Контракт из реестра контрактов ЕИС (44-ФЗ), разобранный из XML.
 */
final readonly class ParsedContract
{
    /**
     * @param  list<ParsedProduct>  $products
     */
    public function __construct(
        public string $registryNumber,
        public string $subject,
        public string $customerName,
        public ?string $customerInn,
        public int $amountKopecks,
        public Carbon $signedAt,
        public ?Carbon $endsAt,
        public ?ParsedSupplier $supplier,
        public array $products,
    ) {}
}
