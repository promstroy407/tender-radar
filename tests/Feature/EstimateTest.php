<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Models\Contract;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\Estimates\PriceMatcher;
use App\Services\Estimates\PriceResolver;
use Database\Seeders\PriceItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
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
        // Построчно нормативную смету не пересчитываем — только итог.
        $this->assertSame(0, EstimateLine::query()->count());

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/estimate')
            ->assertOk()
            ->assertSee("989\u{00A0}896,38\u{00A0}₽")
            ->assertSee('33,0 %')
            ->assertSee('без материалов, оборудования и НДС');

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id)
            ->assertSee("989\u{00A0}896,38\u{00A0}₽");
    }

    public function test_excel_estimate_is_read_matched_and_summed(): void
    {
        $contract = Contract::factory()->hot()->create(['amount_kopecks' => 10_000_000]);

        $this->actingAs($this->admin())
            ->post('/tenders/'.$contract->id.'/estimate/files', ['files' => [$this->xlsx([
                ['№', 'Наименование', 'Ед. изм.', 'Количество', 'Материалы, руб.'],
                [1, 'Извещатель пожарный дымовой ИП 212-141', 'шт', 20, 15000],
                [2, 'Прокладка кабеля КПСЭнг(А)-FRLS по стенам', '100 м', 1.5, 9000.5],
                [3, 'Устройство бетонной подготовки', 'м3', 4, 50000],
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
            ->assertSee('Не посчитано позиций: 1');

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
        $unit = $this->app->make(PriceResolver::class)->unitPrice($line->priceItem, $line->file->estimate->contract->region_code);
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
            '*pdftotext*' => Process::result(''),
            '*pdfinfo*' => Process::result("Title: смета\nPages:          2\n"),
            '*pdftoppm*' => Process::result(''),
            '*tesseract*' => Process::sequence()
                ->push(Process::result("ЛОКАЛЬНЫЙ СМЕТНЫЙ РАСЧЕТ № 02-01-01\nМонтаж извещателей 12 шт\n"))
                ->push(Process::result("Итого по разделу 1 98 000,00\nВСЕГО по смете 1 041 495,44\nв т.ч. материалы 51 599,06\nоборудование 0\n")),
        ]);
        $contract = Contract::factory()->create(['amount_kopecks' => 300_000_000]);

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('СМ-СПС скан.pdf', 50, 'application/pdf')],
        ]);

        $file = EstimateFile::query()->sole();
        $this->assertSame(EstimateFileStatus::Parsed, $file->status);
        $this->assertSame(104_149_544, $file->norm_total_kopecks);
        $this->assertSame(5_159_906, $file->norm_materials_kopecks);
        Process::assertRan(fn ($process): bool => str_contains(implode(' ', (array) $process->command), 'tesseract') && str_contains(implode(' ', (array) $process->command), 'rus'));
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
        $contract = Estimate::query()->sole()->contract;

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
