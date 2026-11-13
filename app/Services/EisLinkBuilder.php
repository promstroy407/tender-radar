<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProcurementLaw;

/**
 * Ссылка на контракт в ЕИС. Вычисляется из номера реестровой записи и закона,
 * поэтому в базе не хранится.
 */
class EisLinkBuilder
{
    private const BASE = 'https://zakupki.gov.ru/epz';

    public function contractUrl(ProcurementLaw $law, string $registryNumber): string
    {
        $section = match ($law) {
            ProcurementLaw::Fz44 => 'contract',
            ProcurementLaw::Fz223 => 'contractfz223',
        };

        return self::BASE.'/'.$section.'/search/results.html?'.http_build_query([
            'searchString' => $registryNumber,
        ]);
    }
}
