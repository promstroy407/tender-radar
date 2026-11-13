<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MoneyFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function amounts(): array
    {
        $nbsp = "\u{00A0}";

        return [
            'ноль' => [0, '0'.$nbsp.'₽'],
            'целые рубли' => [1_840_000_000, '18'.$nbsp.'400'.$nbsp.'000'.$nbsp.'₽'],
            'с копейками' => [123_456_789, '1'.$nbsp.'234'.$nbsp.'567,89'.$nbsp.'₽'],
            'копейки с ведущим нулём' => [100_005, '1'.$nbsp.'000,05'.$nbsp.'₽'],
            'меньше тысячи' => [99_900, '999'.$nbsp.'₽'],
            'отрицательная' => [-250_000, '−2'.$nbsp.'500'.$nbsp.'₽'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_formats_kopecks_exactly(int $kopecks, string $expected): void
    {
        $this->assertSame($expected, (new MoneyFormatter)->format($kopecks));
    }
}
