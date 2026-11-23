<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Data\Eis\ParsedContract;
use App\Data\Eis\ParsedProduct;
use App\Services\Eis\ContractClassifier;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractClassifierTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string|null}>
     */
    public static function cases(): array
    {
        // предмет, ОКПД2, позиция, ожидание «вид [разделы]» или null — не наш
        return [
            'электропроводка по ОКПД2' => ['Выполнение работ по замене электропроводки', '43.21.10.290', 'Замена', 'profile [eom]'],
            'видео и СКУД по тексту' => ['Монтаж системы видеонаблюдения и СКУД', '80.20.10.000', 'Монтаж', 'profile [skud,video]'],
            'наружное освещение' => ['Устройство наружного освещения', '42.22.22.000', 'Работы', 'profile [eom]'],
            'пожарка и оповещение' => ['Монтаж АПС и СОУЭ в здании', '43.29.19.000', 'Монтаж', 'profile [aps,soue]'],
            'капремонт' => ['Капитальный ремонт здания школы', '41.20.40.900', 'Работы', 'general [eom,ss,aps]'],
            'ремонт помещений' => ['Выполнение работ по ремонту помещений', '43.39.19.190', 'Ремонт', 'general [eom,ss,aps]'],
            'реконструкция с электрикой' => ['Реконструкция здания, включая электроснабжение', '41.20.40.000', 'Работы', 'profile [eom]'],
            'лекарства' => ['Поставка лекарственного препарата', '21.20.10.132', 'Этамзилат', null],
            'поставка светильников' => ['Поставка светильников светодиодных', '27.40.39.110', 'Светильник', null],
            'только обслуживание' => ['Техническое обслуживание систем пожарной сигнализации', '80.20.10.000', 'Обслуживание', null],
            'только проект' => ['Разработка проектной документации на электроснабжение', '71.12.12.000', 'Проект', null],
            'дорога' => ['Ремонт автомобильной дороги', '42.11.20.000', 'Ремонт', null],
            'электронный аукцион не электрика' => ['Ремонт кабинета, электронная очередь', '33.12.19.000', 'Ремонт', null],
        ];
    }

    #[DataProvider('cases')]
    public function test_classifies_by_subject_and_okpd2(string $subject, string $okpd2, string $product, ?string $expected): void
    {
        $contract = new ParsedContract(
            registryNumber: '1',
            subject: $subject,
            customerName: 'Заказчик',
            customerInn: null,
            amountKopecks: 100_000_000,
            signedAt: Carbon::parse('2026-11-20'),
            endsAt: null,
            supplier: null,
            products: [new ParsedProduct($okpd2, $product)],
        );

        $result = (new ContractClassifier)->classify($contract);

        $this->assertSame($expected, $result === null ? null : $result->kind->value.' ['.implode(',', array_map(
            fn ($section): string => $section->value,
            $result->sections,
        )).']');
    }
}
