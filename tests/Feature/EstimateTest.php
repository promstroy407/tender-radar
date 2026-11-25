<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Jobs\ParseEstimateFile;
use App\Models\Contract;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\EstimateMaterial;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\Estimates\PriceMatcher;
use App\Services\Estimates\PriceResolver;
use Database\Seeders\PriceItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class EstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(PriceItemSeeder::class);

        // Стартовый перечень без цен (решение 16.11.2026); для проверки расчёта
        // вписываем цены так, как их впишет заказчик в «Прайсе работ».
        foreach (self::HAND_PRICES as $name => $kopecks) {
            PriceItem::query()->where('name', $name)->update(['work_price_kopecks' => $kopecks]);
        }
    }

    /** @var array<string, int> */
    private const HAND_PRICES = [
        'Монтаж дымового извещателя' => 30_000,
        'Прокладка кабеля шлейфа' => 3_000,
        'Монтаж приёмно-контрольного прибора' => 100_000,
        'Монтаж сирены' => 130_000,
    ];

    public function test_customer_normative_estimate_gives_works_without_materials(): void
    {
        // Фрагмент выгрузки ПК РИК: итоги разделов, затем итог сметы и «Итого по смете» с НДС при УСН.
        $contract = Contract::factory()->create(['amount_kopecks' => 300_000_000]);

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', ['files' => [$this->xlsx([
            ['Локальный сметный расчёт (смета) № 1'],
            ['№ п/п', 'Обоснование', 'Наименование работ и затрат', 'Ед. изм.', 'Количество'],
            ['1', 'ГЭСНм08-02-399-01', 'Прокладка кабеля до 35 кВ в проложенных трубах', '100 м', 1.2],
            [null, null, 'Средний разряд работы 4,0', 'чел.-ч', 54],
            [null, null, 'Итого по разделу 1', null, 282178.99],
            [null, null, 'ВСЕГО по смете', 1041495.44, null, 'ВСЕГО по смете'],
            [null, null, 'Всего - ОТ', 402641.97, null, 'Всего - ОТ'],
            [null, null, 'Всего - МР', 51599.06, null, 'Всего - МР'],
            [null, null, 'Всего оборудование', null, null, 'Всего оборудование'],
            [null, null, 'Всего - Итоговая сметная стоимость (без НДС)', 1041495.44],
            [null, null, 'Компенсация НДС при УСН', 35967.56],
            [null, null, 'ИТОГО ПО СМЕТЕ', 1077463.0],
        ])]]);

        $file = EstimateFile::query()->sole();
        $this->assertSame(104_149_544, $file->norm_total_kopecks);
        $this->assertSame(5_159_906, $file->norm_materials_kopecks);
        $this->assertSame(98_989_638, $file->normativeTotals()?->worksKopecks());
        // Работы сметы (строки ГЭСН) — в позиции: их считаем по нашему прайсу для сравнения.
        $this->assertSame(1, EstimateLine::query()->count());
        $this->assertSame(120_000, EstimateLine::query()->sole()->quantity_milli);

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/estimate')
            ->assertOk()
            ->assertSee("989\u{00A0}896,38\u{00A0}₽")
            ->assertSee('33,0 %')
            ->assertSee('без материалов, оборудования и НДС');

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id)
            ->assertSee("989\u{00A0}896,38\u{00A0}₽");
    }

    public function test_tray_and_fittings_are_taken_from_customer_estimate(): void
    {
        $contract = Contract::factory()->create(['amount_kopecks' => 300_000_000]);

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', ['files' => [$this->xlsx([
            ['№ п/п', 'Обоснование', 'Наименование работ и затрат', 'Ед. изм.', 'Количество'],
            ['1', 'ГЭСНм08-02-394-01', 'Лотки металлические шириной до 200 мм', '100 м', 0.3],
            ['2', '21.1.08.03-0573', 'Лоток перфорированный 200х50 мм', 'м', 30, 450.5, 13515],
            ['3', 'ТЦ_08.3.05.02_77_0000000000_26.03.2026_02_1.1', 'Крышка лотка 200 мм Ценообразование: 300/1,22*1,04=255,74', 'м', 30, 255.74, 7672.2],
            ['4', '08.1.02.11-0001', 'Кронштейн настенный 200 мм', '10 шт', 1.5, 1200, 1800],
            ['5', '01.7.15.03-0042', 'Стяжка металлическая 4,6х200', '100 шт', 2, 150, 300],
            ['6', 'ТЦ_61.2.04.07_77', 'Извещатель пожарный дымовой', 'шт', 10, 1500, 15000],
            [null, null, 'ВСЕГО по смете', 100000.0, null, 'ВСЕГО по смете'],
            [null, null, 'Всего - МР', 38287.2, null, 'Всего - МР'],
        ])]]);

        $this->assertSame(5, EstimateMaterial::query()->count());

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/materials')
            ->assertOk()
            ->assertSeeInOrder([
                'Лоток перфорированный', "13\u{00A0}515\u{00A0}₽", "30 м × 450,50\u{00A0}₽",
                'Комплектующие лотка', 'Крышка лотка 200 мм', 'Кронштейн настенный 200 мм', '15 шт',
                'Стяжки металлические', '200 шт',
            ])
            ->assertDontSee('Извещатель пожарный дымовой')
            ->assertDontSee('Ценообразование')
            // 13 515 + 7 672,20 + 1 800 + 300 = 23 287,20 ₽; с извещателями — 38 287,20 ₽ = итог МР.
            ->assertSee("23\u{00A0}287,20\u{00A0}₽")
            ->assertSee("38\u{00A0}287,20\u{00A0}₽")
            ->assertSee('100,0 %');

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/estimate')->assertSee('Лоток и комплектующие по смете заказчика');
        $this->actingAs(User::factory()->create())->get('/tenders/'.$contract->id.'/materials')->assertForbidden();
    }

    public function test_estimate_read_before_materials_can_be_reread(): void
    {
        Queue::fake();
        $file = EstimateFile::factory()->create(['norm_total_kopecks' => 100_000, 'norm_materials_kopecks' => 10_000, 'original_name' => 'smeta.xlsx']);
        $contract = Contract::query()->findOrFail($file->estimate->contract_id);

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/materials')
            ->assertOk()
            ->assertSee('Перечитать сметы');

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/materials/reread')
            ->assertRedirect('/tenders/'.$contract->id.'/materials');
        Queue::assertPushed(ParseEstimateFile::class, 1);
    }

    public function test_excel_estimate_is_read_matched_and_summed(): void
    {
        $contract = Contract::factory()->hot()->create(['amount_kopecks' => 10_000_000]);

        $this->actingAs($this->admin())
            ->post('/tenders/'.$contract->id.'/estimate/files', ['files' => [$this->xlsx([
                ['№', 'Наименование', 'Ед. изм.', 'Количество', 'Материалы, руб.'],
                [1, 'Извещатель пожарный дымовой ИП 212-141', 'шт', 20, 15000],
                [2, 'Прокладка кабеля КПСЭнг(А)-FRLS по стенам', '100 м', 1.5, 9000.5],
                [3, 'Устройство бетонной подготовки', 'шт', 4, 50000],
                [4, 'Прибор приёмно-контрольный Сигнал-20П', 'шт', 1, null],
            ])]])
            ->assertRedirect('/tenders/'.$contract->id.'/estimate');

        $file = EstimateFile::query()->sole();
        $this->assertSame(EstimateFileStatus::Parsed, $file->status);

        $lines = EstimateLine::query()->with('priceItem')->orderBy('position')->get()->all();
        $this->assertCount(4, $lines);
        $this->assertSame('Монтаж дымового извещателя', $lines[0]->priceItem?->name);
        $this->assertSame('Прокладка кабеля шлейфа', $lines[1]->priceItem?->name);
        $this->assertSame(150_000, $lines[1]->quantity_milli);
        $this->assertNull($lines[2]->priceItem);
        $this->assertSame('Монтаж приёмно-контрольного прибора', $lines[3]->priceItem?->name);

        // Только работы: 20×300 + 150×30 + 1×1000 = 11 500 ₽ из 100 000 ₽ контракта. Материалы не считаются.
        $this->assertSame(1_500_000, $lines[0]->material_kopecks);
        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/estimate')
            ->assertOk()
            ->assertSee("11\u{00A0}500\u{00A0}₽")
            ->assertDontSee("74\u{00A0}000,50")
            ->assertSee('11,5 %')
            ->assertSee('Не посчитано позиций: 1')
            // Что не посчитано — видно списком с причиной.
            ->assertSeeInOrder(['Не посчитано (1)', 'Устройство бетонной подготовки', '+ в прайс']);

        // Черновик по строке демонтажа начинается с «Демонтаж», иначе он с ней не сопоставится.
        $this->actingAs($this->admin())->get('/prices/new?'.http_build_query(['name' => 'Короба пластмассовые (Демонтаж короба)', 'unit' => '100 м']))
            ->assertOk()
            ->assertSee('value="Демонтаж: Короба пластмассовые (Демонтаж короба)"', false);

        // «+ в прайс»: форма заполнена по строке, после сохранения строка посчитана.
        $this->actingAs($this->admin())->get('/prices/new?'.http_build_query(['name' => 'Устройство бетонной подготовки', 'unit' => 'шт', 'back' => $file->estimate_id]))
            ->assertOk()
            ->assertSee('value="Устройство бетонной подготовки"', false)
            ->assertSee('устрой бетонн подгот');

        $this->actingAs($this->admin())->post('/prices', [
            'section' => 'electric', 'name' => 'Бетонная подготовка', 'unit' => 'шт', 'price_rubles' => '500',
            'keywords' => 'бетонн подгот', 'is_active' => '1', 'back' => $file->estimate_id,
        ])->assertRedirect('/tenders/'.$contract->id.'/estimate#unpriced');

        $this->assertNotNull(EstimateLine::query()->where('name', 'like', 'Устройство бетонной%')->value('price_item_id'));
        $this->actingAs(User::factory()->create())->get('/prices/new?name=x')->assertForbidden();

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id)
            ->assertOk()
            ->assertSee("11\u{00A0}500\u{00A0}₽");
    }

    public function test_matching_rules_from_real_estimates(): void
    {
        $items = PriceItem::query()->get();
        $matcher = $this->app->make(PriceMatcher::class);

        // Короткое ключевое слово «вру» не должно находиться во «вручную».
        $this->assertNotSame('Монтаж ВРУ или распределительного шкафа', $matcher->match('Разработка грунта вручную в траншеях', 'м', $items)?->name);
        $this->assertSame('Монтаж ВРУ или распределительного шкафа', $matcher->match('Монтаж ВРУ-1 вводного', 'шт', $items)?->name);
        $this->assertSame('Установка однополюсного автомата', $matcher->match('Авт. выкл. NXM-250S/3P, 200А', 'шт.', $items)?->name);
        $this->assertSame('Установка УЗО или дифавтомата', $matcher->match('Дифференц. автомат NB1L-40, 2P', 'шт.', $items)?->name);
        $this->assertSame('Прокладка кабеля в траншее', $matcher->match('Прокладка кабеля ПС в траншее', 'м', $items)?->name);
        $this->assertSame('Монтаж дымового извещателя', $matcher->match('Установка наружных извещателей', 'шт.', $items)?->name);
        // Объём с метрами прайса не сопоставляется.
        $this->assertNull($matcher->match('Засыпка кабеля песком', '100 м3', $items));

        // Демонтаж — только позиции демонтажа (строки из сметы тендера 2634), монтаж — только монтаж.
        $this->assertSame('Демонтаж извещателя', $matcher->match('Извещатель ПС автоматический: дымовой, фотоэлектрический (Демонтаж извещателя дымового "ИП212-46")', 'шт', $items)?->name);
        $this->assertSame('Демонтаж кабеля или провода', $matcher->match('Провод в лотках, сечением: до 6 мм2 (Демонтаж кабеля КПСВЭВнг 2х0,75)', '100 м', $items)?->name);
        $this->assertSame('Демонтаж кабельного короба или кабель-канала', $matcher->match('Короба пластмассовые: шириной до 40 мм (Демонтаж короба 12х12)', '100 м', $items)?->name);
        $this->assertSame('Демонтаж оповещателя, табло или указателя', $matcher->match('Световые настенные указатели (Демонтаж Светоуказатель "ВЫХОД")', 'шт', $items)?->name);
        $this->assertSame('Демонтаж приёмно-контрольного прибора', $matcher->match('Приборы ПС приемно-контрольные, пусковые: блок базовый (Демонтаж приемно-контрольного прибора "С2000")', 'шт', $items)?->name);
        $this->assertSame('Монтаж дымового извещателя', $matcher->match('Извещатель ПС автоматический: дымовой, фотоэлектрический', 'шт', $items)?->name);
        $this->assertSame('Монтаж оповещателя или табло «Выход»', $matcher->match('Световые настенные указатели', 'шт', $items)?->name);
        // «Магнитоконтактный» в норме на тепловой извещатель — это пожарка, не охранный датчик.
        $this->assertSame('Монтаж дымового извещателя', $matcher->match('Извещатель ПС автоматический: тепловой электро-контактный, магнитоконтактный в нормальном исполнении', 'шт', $items)?->name);
    }

    public function test_line_can_be_matched_manually_or_skipped(): void
    {
        $line = EstimateLine::factory()->create(['name' => 'Непонятная позиция', 'quantity_milli' => 3_000]);
        $item = PriceItem::query()->where('name', 'Монтаж сирены')->sole();

        $this->actingAs($this->admin())
            ->put('/estimate-lines/'.$line->id, ['price_item' => (string) $item->id, 'filter' => 'attention'])
            ->assertRedirect();

        $line->refresh()->load('priceItem');
        $this->assertSame(LineMatch::Manual, $line->match);
        $this->assertNotNull($line->priceItem);
        // Норм в базе нет — берётся цена, вписанная вручную: 3 × 1 300 ₽.
        $unit = $this->app->make(PriceResolver::class)->unitPrice($line->priceItem, $line->estimate->regionCode());
        $this->assertSame(390_000, $line->workKopecks($unit));

        $this->actingAs($this->admin())->put('/estimate-lines/'.$line->id, ['price_item' => 'skip']);
        $line->refresh();
        $this->assertSame(LineMatch::Skip, $line->match);
        $this->assertNull($line->workKopecks(130_000));

        $this->actingAs($this->admin())->put('/estimate-lines/'.$line->id, ['price_item' => '999999'])
            ->assertSessionHasErrors('price_item');
    }

    public function test_zip_is_unpacked_and_unsupported_files_are_explained(): void
    {
        $contract = Contract::factory()->create();
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('Смета ЭОМ.xlsx', (string) file_get_contents($this->xlsx([
            ['Наименование', 'Ед.', 'Кол-во'],
            ['Светильник светодиодный потолочный', 'шт', 10],
        ])->getRealPath()));
        $zip->addFromString('Пояснительная записка.docx', 'doc');
        $zip->addFromString('Смета.gsfx', 'grand');
        $zip->close();

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [new UploadedFile($zipPath, 'Документация.zip', 'application/zip', null, true)],
        ]);

        $files = EstimateFile::query()->orderBy('id')->get()->all();
        $this->assertCount(3, $files);
        $this->assertSame(EstimateFileStatus::Parsed, $files[0]->status);
        $this->assertSame('Документация.zip → Смета ЭОМ.xlsx', $files[0]->original_name);
        $this->assertStringContainsString('Word', (string) $files[1]->error);
        $this->assertStringContainsString('ГРАНД-Смет', (string) $files[2]->error);
        $this->assertSame(1, EstimateLine::query()->count());
    }

    public function test_pdf_is_read_through_pdftotext(): void
    {
        Process::fake([
            '*pdftotext*' => Process::result(
                "  № п/п   Наименование                                  Ед. изм.     Кол-во\n"
                ."  1       Монтаж IP-камеры видеонаблюдения              шт           12\n"
                ."  2       Видеорегистратор сетевой 16 каналов           шт           1\n",
            ),
        ]);
        $contract = Contract::factory()->create();

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('ведомость.pdf', 50, 'application/pdf')],
        ]);

        $lines = EstimateLine::query()->with('priceItem')->orderBy('position')->get()->all();
        $this->assertCount(2, $lines);
        $this->assertSame('Монтаж IP-камеры', $lines[0]->priceItem?->name);
        $this->assertSame('Монтаж и настройка видеорегистратора', $lines[1]->priceItem?->name);
    }

    public function test_scanned_estimate_is_recognized_and_totals_taken(): void
    {
        // Скан сметы заказчика: текста нет — страницы распознаются Tesseract.
        Process::fake([
            // Только разрывы страниц и номер листа — текстового слоя нет.
            '*pdftotext*' => Process::result("\f Лист 1\f\f"),
            '*pdfinfo*' => Process::result("Title: смета\nPages:          2\n"),
            '*pdftoppm*' => Process::result(''),
            '*tesseract*' => Process::sequence()
                ->push(Process::result("ЛОКАЛЬНЫЙ СМЕТНЫЙ РАСЧЕТ № 02-01-01\nМонтаж извещателей 12 шт\n"))
                // Итоги формы 421/пр (ГРАНД-Смета) так, как их отдаёт распознавание: разряды
                // склеены, в конце строк — скобки от линий таблицы.
                ->push(Process::result(
                    "Материалы 1471 231,68\nОборудование 509 695,75)\nВсего по позиции 3166,40 221 648,12\n"
                    ."Итоги по смете:\nВсего прямые затраты (справочно) 3905 627,81\nМатериалы 1485 345,19\n"
                    ."Монтажные работы 7330 845,66\nматериалы 1485 345,19\nОборудование 509 695,75)\n"
                    ."Инженерное оборудование 509 695,75\nВсего 7840 541,41\nСоставил:\n",
                )),
        ]);
        $contract = Contract::factory()->create(['amount_kopecks' => 300_000_000]);

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('СМ-СПС скан.pdf', 50, 'application/pdf')],
        ]);

        $file = EstimateFile::query()->sole();
        $this->assertSame(EstimateFileStatus::Parsed, $file->status);
        $this->assertSame(784_054_141, $file->norm_total_kopecks);
        $this->assertSame(148_534_519, $file->norm_materials_kopecks);
        // Работы = 7 840 541,41 − 1 485 345,19 − 509 695,75.
        $this->assertSame(584_550_047, $file->normativeTotals()?->worksKopecks());
        Process::assertRan(fn ($process): bool => str_contains(implode(' ', (array) $process->command), 'tesseract') && str_contains(implode(' ', (array) $process->command), 'rus'));
    }

    public function test_interrupted_parsing_does_not_hang_as_pending(): void
    {
        $file = EstimateFile::factory()->create(['status' => EstimateFileStatus::Pending]);

        (new ParseEstimateFile($file))->failed(new \RuntimeException('killed'));

        $file->refresh();
        $this->assertSame(EstimateFileStatus::Failed, $file->status);
        $this->assertStringContainsString('Прочитать заново', (string) $file->error);
    }

    public function test_scan_without_text_gets_clear_message(): void
    {
        Process::fake([
            '*pdftotext*' => Process::result(''),
            '*pdfinfo*' => Process::result("Pages: 1\n"),
            '*pdftoppm*' => Process::result(''),
            '*tesseract*' => Process::result(''),
        ]);
        $contract = Contract::factory()->create();

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('чертёж.pdf', 50, 'application/pdf')],
        ]);

        $this->assertStringContainsString('чертёж', (string) EstimateFile::query()->sole()->error);
    }

    public function test_export_to_excel_with_formulas(): void
    {
        $line = EstimateLine::factory()->create(['quantity_milli' => 2_500, 'material_kopecks' => 10_050]);
        $line->update(['price_item_id' => PriceItem::query()->where('name', 'Монтаж сирены')->value('id')]);
        $contract = Contract::query()->findOrFail(Estimate::query()->sole()->contract_id);

        $response = $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/estimate/export');
        $response->assertOk();

        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);
        $sheet = IOFactory::load($base->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(2.5, $sheet->getCell('D5')->getValue());
        $this->assertSame('=IF(F5="",0,ROUND(D5*F5,2))', $sheet->getCell('G5')->getValue());
        // 2,5 × 1 300 = 3 250 ₽ работы; материал в выгрузку не идёт.
        $this->assertEqualsWithDelta(3250.0, $sheet->getCell('G6')->getCalculatedValue(), 0.001);
        $this->assertNull($sheet->getCell('H5')->getValue());
    }

    public function test_upload_validation_and_access(): void
    {
        $contract = Contract::factory()->create();

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('files.0');

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [])
            ->assertSessionHasErrors('files');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/tenders/'.$contract->id.'/estimate')->assertForbidden();
        $this->actingAs($user)->get('/prices')->assertForbidden();
        $this->actingAs($user)->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('a.xlsx', 10)],
        ])->assertForbidden();
    }

    public function test_price_list_edit_add_and_validate(): void
    {
        $item = PriceItem::query()->where('name', 'Монтаж кабельного лотка')->sole();
        $this->assertNull($item->work_price_kopecks);

        $this->actingAs($this->admin())->get('/prices')
            ->assertOk()
            ->assertSee('Монтаж дымового извещателя')
            ->assertSee('нет цены');

        $this->actingAs($this->admin())->put('/prices/'.$item->id, [
            'section' => 'electric', 'name' => 'Монтаж кабельного лотка', 'unit' => 'м',
            'price_rubles' => '1 250,5', 'keywords' => 'Лоток, лотк', 'is_active' => '1',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(125_050, $item->work_price_kopecks);
        $this->assertSame('лоток, лотк', $item->keywords);

        $this->actingAs($this->admin())->post('/prices', [
            'section' => 'fire', 'name' => 'Пусконаладка АПС', 'unit' => 'услуга',
            'price_rubles' => '', 'keywords' => 'пусконалад', 'is_active' => '1',
        ])->assertRedirect('/prices');
        $this->assertNull(PriceItem::query()->where('name', 'Пусконаладка АПС')->value('work_price_kopecks'));

        $this->actingAs($this->admin())->post('/prices', [
            'section' => 'space', 'name' => '', 'unit' => 'литр', 'price_rubles' => '12,345', 'keywords' => '',
        ])->assertSessionHasErrors(['section', 'name', 'unit', 'price_rubles', 'keywords']);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'smeta.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
