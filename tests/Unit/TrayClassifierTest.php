<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\MaterialGroup;
use App\Services\Estimates\TrayClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrayClassifierTest extends TestCase
{
    /**
     * @return array<string, array{string, MaterialGroup|null}>
     */
    public static function names(): array
    {
        return [
            'перфорированный' => ['Лоток перфорированный 200х50 мм, толщина 0,7 мм', MaterialGroup::TrayPerforated],
            'неперфорированный' => ['Лоток металлический неперфорированный 100х50', MaterialGroup::TraySolid],
            'лестничный' => ['Лоток лестничный 300х100', MaterialGroup::TrayOther],
            'кабельрост' => ['Кабельрост 400 мм', MaterialGroup::TrayOther],
            'крышка' => ['Крышка для лотка 200 мм', MaterialGroup::Fittings],
            'поворот' => ['Поворот лотка 90° 200х50', MaterialGroup::Fittings],
            'соединитель' => ['Соединитель лотка', MaterialGroup::Fittings],
            'кронштейн' => ['Кронштейн настенный 200 мм', MaterialGroup::Fittings],
            'шпилька' => ['Шпилька резьбовая М8', MaterialGroup::Fittings],
            'металлическая стяжка' => ['Стяжка металлическая 4,6х200 мм', MaterialGroup::Ties],
            'нейлоновая стяжка' => ['Стяжка кабельная нейлоновая', null],
            'кабель-канал' => ['Короб кабельный (кабель-канал) ПВХ с крышкой', null],
            'кабель' => ['Кабель КПСнг(А)-FRLS 1х2х0,75', null],
        ];
    }

    #[DataProvider('names')]
    public function test_group(string $name, ?MaterialGroup $group): void
    {
        $this->assertSame($group, (new TrayClassifier)->classify($name));
    }

    public function test_bare_brackets_count_only_with_trays(): void
    {
        $this->assertNull((new TrayClassifier)->classify('Кронштейн настенный', false));
    }
}
