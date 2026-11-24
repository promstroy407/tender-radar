<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\DocumentKind;
use App\Services\Eis\DocumentKindClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentKindClassifierTest extends TestCase
{
    /**
     * @return array<string, array{string, DocumentKind}>
     */
    public static function names(): array
    {
        // Реальные имена файлов из извещения ЕИС (тендер на охранное ТВ, СКУД, АПС).
        return [
            'смета архивом' => ['12.2. Сметная документация_10-05_566_28_08_2025(ver1).ZIP', DocumentKind::Estimate],
            'ССР' => ['12.3. Сводный сметный расчет_10-05_566_28_08_2025(ver1).ZIP', DocumentKind::Estimate],
            'ПНР' => ['12.4. Пусконаладочные работы_10-05_566_28_08_2025(ver1).ZIP', DocumentKind::Estimate],
            'ЛСР' => ['ЛСР 02-01-01 ЭОМ.xlsx', DocumentKind::Estimate],
            'дефектная ведомость' => ['12.5 Акт содержащий перечень дефектов_10-05_566_28_08_2025(ver1).pdf', DocumentKind::WorksList],
            'ВОР' => ['ВОР.xlsx', DocumentKind::WorksList],
            'рабочая документация' => ['12.1. Рабочая документация РД-ОС_10-05_566_28_08_2025(ver1).ZIP', DocumentKind::Project],
            'том ИОС' => ['Том 5.1 ИОС1.pdf', DocumentKind::Project],
            'проект контракта' => ['2 Проект контракта ОАЭФ-157_10-05_566_28_08_2025(ver6).doc', DocumentKind::Other],
            'НМЦК' => ['1 Обоснование НМЦК ОАЭФ-157_10-05_566_28_08_2025(ver2).XLS', DocumentKind::Other],
            'описание объекта' => ['3 Описание объекта закупки ОАЭФ-157.doc', DocumentKind::Other],
            'требования к заявке' => ['4 Требования к содержанию, составу заявки.doc', DocumentKind::Other],
            'реквизиты' => ['5 Реквизиты счета для перечисления обеспечения.docx', DocumentKind::Other],
            'акт площадки' => ['12.6 Акт приема-передачи строительной площадки.docx', DocumentKind::Other],
            'ответ на запрос' => ['Ответ на запрос № 1 ОАЭФ-157.docx', DocumentKind::Other],
            'изменения' => ['Изменения к ОАЭФ-157.doc', DocumentKind::Other],
        ];
    }

    #[DataProvider('names')]
    public function test_kind_by_file_name(string $name, DocumentKind $expected): void
    {
        $this->assertSame($expected, (new DocumentKindClassifier)->classify($name));
    }
}
