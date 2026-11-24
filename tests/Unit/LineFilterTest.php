<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Estimates\LineFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LineFilterTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null, bool}>
     */
    public static function lines(): array
    {
        return [
            'заголовок листа' => ['ЭКСПЛИКАЦИЯ', '828 383 838 384 Г', true],
            'заголовок без единицы' => ['Спецификация', 'шт', true],
            'кусок переноса' => ['регистрации программы', 'шт', true],
            'завод-изготовитель' => ['ТД «ТИНКО»', 'шт', true],
            'изготовитель ООО' => ['ООО "Рубеж"', 'шт', true],
            'голый артикул' => ['PR03.0072', 'м', true],
            'цифры штампа в единице' => ['Резервируемый источник питания', '828 383 838 384', true],
            'оборудование' => ['Резервируемый источник питания', 'шт', false],
            'марка заглавными' => ['АКОР-УОП', 'шт', false],
            'марка кабеля' => ['КПСнг(А)-FRLSLTx', 'м', false],
            'короткая марка' => ['Молния-24В', 'шт', false],
            'строчная, но длинная' => ['кабель силовой с медными жилами ВВГнг', 'м', false],
            'работа' => ['Прокладка кабеля по стенам', '100 м', false],
        ];
    }

    #[DataProvider('lines')]
    public function test_junk_is_recognised(string $name, ?string $unit, bool $junk): void
    {
        $this->assertSame($junk, (new LineFilter)->isJunk($name, $unit));
    }
}
