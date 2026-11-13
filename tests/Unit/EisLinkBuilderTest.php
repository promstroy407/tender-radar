<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProcurementLaw;
use App\Services\EisLinkBuilder;
use PHPUnit\Framework\TestCase;

class EisLinkBuilderTest extends TestCase
{
    public function test_builds_search_link_for_44_fz(): void
    {
        $this->assertSame(
            'https://zakupki.gov.ru/epz/contract/search/results.html?searchString=3165001234526000012',
            (new EisLinkBuilder)->contractUrl(ProcurementLaw::Fz44, '3165001234526000012'),
        );
    }

    public function test_builds_search_link_for_223_fz(): void
    {
        $this->assertSame(
            'https://zakupki.gov.ru/epz/contractfz223/search/results.html?searchString=57700000000260001',
            (new EisLinkBuilder)->contractUrl(ProcurementLaw::Fz223, '57700000000260001'),
        );
    }
}
