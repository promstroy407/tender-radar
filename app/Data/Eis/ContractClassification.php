<?php

declare(strict_types=1);

namespace App\Data\Eis;

use App\Enums\ContractKind;
use App\Enums\WorkSection;

/** Итог отбора: вид тендера и наши разделы в нём. */
final readonly class ContractClassification
{
    /**
     * @param  list<WorkSection>  $sections
     */
    public function __construct(
        public ContractKind $kind,
        public array $sections,
    ) {}
}
