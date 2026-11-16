<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Estimates\NumberParser;
use App\Services\Estimates\TableExtractor;
use App\Services\Estimates\UnitNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EstimateParsingTest extends TestCase
{
    /**
     * @return array<string, array{0: string|int|float|null, 1: int|null}>
     */
    public static function quantities(): array
    {
        return [
            'целое' => ['12', 12_000],
            'с запятой' => ['125,5', 125_500],
            'с пробелом тысяч' => ["1\u{00A0}234,25", 1_234_250],
            'точка' => ['0.125', 125],
            'округление' => ['1,2345', 1_235],
            'из Excel' => [125.5, 125_500],
            'целое из Excel' => [7, 7_000],
            'тысячи точками' => ['1.234.567', 1_234_567_000],
            'ноль' => ['0', null],
            'текст' => ['шт', null],
            'пусто' => [null, null],
        ];
    }

    #[DataProvider('quantities')]
    public function test_quantity_to_milli(string|int|float|null $input, ?int $expected): void
    {
        $this->assertSame($expected, (new NumberParser)->toMilli($input));
    }

    public function test_money_to_kopecks(): void
    {
        $parser = new NumberParser;

        $this->assertSame(123_456, $parser->toKopecks('1 234,56'));
        $this->assertSame(123_457, $parser->toKopecks('1234.565'));
        $this->assertSame(150_000, $parser->toKopecks('1500 руб.'));
        $this->assertNull($parser->toKopecks('-5'));
    }

    public function test_units(): void
    {
        $units = new UnitNormalizer;

        $this->assertSame(['kind' => 'length', 'multiplier' => 100], $units->parse('100 м'));
        $this->assertSame(['kind' => 'length', 'multiplier' => 1], $units->parse('м.п.'));
        $this->assertSame(['kind' => 'length', 'multiplier' => 1000], $units->parse('км'));
        $this->assertSame(['kind' => 'piece', 'multiplier' => 1], $units->parse('шт.'));
        $this->assertSame(['kind' => 'piece', 'multiplier' => 10], $units->parse('10 шт'));
        $this->assertNull($units->parse('Кабель ВВГ'));
        $this->assertTrue($units->compatible('100 м', 'м'));
        $this->assertFalse($units->compatible('шт', 'м'));
        $this->assertTrue($units->compatible(null, 'м'));
        $this->assertFalse($units->compatible('100 м3', 'шт'));
        $this->assertTrue($units->isService('чел.-ч'));
        $this->assertTrue($units->isService('%'));
        $this->assertFalse($units->isService('м'));
    }

    public function test_short_header_and_estimate_service_rows(): void
    {
        // Шапка «ЕИ» (как в субподрядной ведомости) и служебные строки сметы ГЭСН.
        $rows = [
            ['№', 'Наименование', 'ЕИ', 'Кол-во'],
            [1, 'Монтаж извещателя теплового', 'шт', 6],
            [2, 'Средний разряд работы 2,0', 'чел.-ч', 154],
            [3, 'НР Земляные работы, выполняемые ручным способом', '%', 89],
            [4, 'Коэффициент к затратам труда рабочих (ЗТ)', '', 1.15],
            [5, 'Разработка грунта вручную в траншеях', '100 м3', 1.031],
            ['', 'СУММА ЗА РАБОТЫ', '', 1000],
        ];

        $lines = $this->extractor()->extract($rows);

        $this->assertSame(['Монтаж извещателя теплового', 'Разработка грунта вручную в траншеях'], array_map(fn ($l) => $l->name, $lines));
        $this->assertSame('шт', $lines[0]->unit);
    }

    public function test_table_with_header(): void
    {
        $rows = [
            ['Локальная смета № 1', null, null, null],
            ['№ п/п', 'Наименование работ и затрат', 'Ед. изм.', 'Кол-во', 'Стоимость материалов'],
            ['1', 'Извещатель пожарный дымовой ИП 212-141', 'шт', '24', '12 000,00'],
            ['2', 'Кабель КПСЭнг(А)-FRLS 1х2х0,5 в гофротрубе', '100 м', '3,55', null],
            ['', 'Итого по разделу', '', '', '12 000,00'],
            ['3', 'Пусто', 'шт', '', ''],
        ];

        $lines = $this->extractor()->extract($rows);

        $this->assertCount(2, $lines);
        $this->assertSame('Извещатель пожарный дымовой ИП 212-141', $lines[0]->name);
        $this->assertSame(24_000, $lines[0]->quantityMilli);
        $this->assertSame(1_200_000, $lines[0]->materialKopecks);
        // 3,55 × 100 м = 355 м
        $this->assertSame('м', $lines[1]->unit);
        $this->assertSame(355_000, $lines[1]->quantityMilli);
    }

    public function test_table_without_header_guesses_columns(): void
    {
        $rows = [
            ['12.', 'Светильник светодиодный потолочный 36 Вт', 'шт.', '48'],
            ['13', 'Кабель ВВГнг-LS 3х2,5', 'м', '1 250,5'],
            ['Примечание: работы выполняются в две смены'],
        ];

        $lines = $this->extractor()->extract($rows);

        $this->assertCount(2, $lines);
        $this->assertSame(48_000, $lines[0]->quantityMilli);
        $this->assertSame('Кабель ВВГнг-LS 3х2,5', $lines[1]->name);
        $this->assertSame(1_250_500, $lines[1]->quantityMilli);
    }

    private function extractor(): TableExtractor
    {
        return new TableExtractor(new NumberParser, new UnitNormalizer);
    }
}
