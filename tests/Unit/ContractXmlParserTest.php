<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Eis\ContractXmlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractXmlParserTest extends TestCase
{
    public function test_parses_contract_supplier_and_products(): void
    {
        $contract = (new ContractXmlParser)->parse(self::xml());

        $this->assertNotNull($contract);
        $this->assertSame('2121504059826000350', $contract->registryNumber);
        $this->assertSame('0108500000426005719', $contract->notificationNumber);
        $this->assertSame('Выполнение работ по замене электропроводки', $contract->subject);
        $this->assertSame('ГБОУ «ШКОЛА № 1» (ВЫМЫШЛЕННЫЙ ЗАКАЗЧИК)', $contract->customerName);
        $this->assertSame('1200000001', $contract->customerInn);
        $this->assertSame(250_000_050, $contract->amountKopecks);
        $this->assertSame('2026-11-20', $contract->signedAt->toDateString());
        $this->assertSame('2027-03-31', $contract->endsAt?->toDateString());

        $this->assertCount(1, $contract->products);
        $this->assertSame('43.21.10.290', $contract->products[0]->okpd2);
        $this->assertSame('Замена электропроводки', $contract->products[0]->name);

        $supplier = $contract->supplier;
        $this->assertNotNull($supplier);
        $this->assertSame('1215000001', $supplier->inn);
        $this->assertSame('121501001', $supplier->kpp);
        $this->assertSame('ООО "ПРИМЕР-МОНТАЖ"', $supplier->name);
        $this->assertSame('info@primer-montazh.test', $supplier->email);
        $this->assertSame('+79000000001', $supplier->phone);
        $this->assertSame('Йошкар-Ола', $supplier->city);
    }

    public function test_not_a_contract_or_broken_xml_gives_null(): void
    {
        $parser = new ContractXmlParser;

        $this->assertNull($parser->parse(''));
        $this->assertNull($parser->parse('<broken'));
        $this->assertNull($parser->parse('<export><notification><id>1</id></notification></export>'));
        $this->assertNull($parser->parse(str_replace('<price>2500000.50</price>', '<price>много</price>', self::xml())));
    }

    public function test_contract_without_supplier_inn_has_no_supplier(): void
    {
        $xml = str_replace('<INN>1215000001</INN>', '', self::xml());

        $contract = (new ContractXmlParser)->parse($xml);

        // ИНН банка из реквизитов счёта не принимается за ИНН победителя.
        $this->assertNull($contract?->supplier);
    }

    public function test_individual_entrepreneur_name(): void
    {
        $xml = preg_replace(
            '#<EGRULInfo>.*</EGRULInfo>#s',
            '<lastName>Иванов</lastName><firstName>Пётр</firstName><middleName>Сергеевич</middleName><INN>121500000012</INN>',
            self::xml(),
        );

        $supplier = (new ContractXmlParser)->parse((string) $xml)?->supplier;

        $this->assertNotNull($supplier);
        $this->assertSame('ИП Иванов Пётр Сергеевич', $supplier->name);
        $this->assertSame('121500000012', $supplier->inn);
    }

    /**
     * @return array<string, array{string, int|null}>
     */
    public static function prices(): array
    {
        return [
            'целые' => ['1000000', 100_000_000],
            'копейки' => ['43267.05', 4_326_705],
            'одна цифра' => ['10.5', 1_050],
            'дробь вниз' => ['8.6534', 865],
            'дробь вверх' => ['8.655', 866],
            'мусор' => ['1 000', null],
            'минус' => ['-5.00', null],
        ];
    }

    #[DataProvider('prices')]
    public function test_kopecks_without_float(string $value, ?int $expected): void
    {
        $this->assertSame($expected, (new ContractXmlParser)->kopecks($value));
    }

    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function phones(): array
    {
        return [
            'с пробелом' => ['7 9278735164', '+79278735164'],
            'с восьмёркой' => ['8 (927) 873-51-64', '+79278735164'],
            'десять цифр' => ['9278735164', '+79278735164'],
            'городской как есть' => ['(8362) 45-67-89 доб. 12', '(8362) 45-67-89 доб. 12'],
            'пусто' => [null, null],
        ];
    }

    #[DataProvider('phones')]
    public function test_phone_normalized(?string $value, ?string $expected): void
    {
        $this->assertSame($expected, (new ContractXmlParser)->phone($value));
    }

    public function test_city_from_address(): void
    {
        $parser = new ContractXmlParser;

        $this->assertSame('Казань', $parser->city('420000, РЕСПУБЛИКА ТАТАРСТАН, г Казань, ул. Баумана, д. 1'));
        $this->assertSame('Москва', $parser->city('125009, Г.МОСКВА, ВН.ТЕР.Г. МУНИЦИПАЛЬНЫЙ ОКРУГ ТВЕРСКОЙ, УЛ ТВЕРСКАЯ'));
        $this->assertNull($parser->city('МОСКОВСКАЯ ОБЛАСТЬ, Р-Н ОДИНЦОВСКИЙ'));
    }

    public static function xml(): string
    {
        return strtr((string) file_get_contents(__DIR__.'/../Fixtures/eis/contract.xml'), [
            '{{REGNUM}}' => '2121504059826000350',
            '{{SUBJECT}}' => 'Выполнение работ по замене электропроводки',
            '{{PRICE}}' => '2500000.50',
            '{{OKPD2}}' => '43.21.10.290',
            '{{PRODUCT}}' => 'Замена электропроводки',
            '{{INN}}' => '1215000001',
        ]);
    }
}
