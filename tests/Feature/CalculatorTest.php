<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstimateFileStatus;
use App\Models\Estimate;
use App\Models\EstimateFile;
use App\Models\EstimateLine;
use App\Models\PriceItem;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\PriceItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Tests\Unit\TzDraftExtractorTest;
use ZipArchive;

class CalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(PriceItemSeeder::class);
        PriceItem::query()->where('name', 'Монтаж дымового извещателя')->update(['work_price_kopecks' => 30_000]);
        Region::factory()->create(['code' => '12', 'name' => 'Республика Марий Эл']);
    }

    public function test_outside_estimate_gives_customer_works_and_ours_separately(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/calculator')->assertOk()->assertSee('Расчётов пока нет');

        $this->actingAs($admin)->post('/calculator', ['name' => 'Школа № 5, АПС', 'region_code' => '12'])
            ->assertRedirect();
        $estimate = Estimate::query()->sole();
        $this->assertTrue($estimate->isCalculation());
        $this->assertSame('12', $estimate->regionCode());

        $this->actingAs($admin)->post('/calculator/'.$estimate->id.'/files', ['files' => [$this->xlsx([
            ['№ п/п', 'Обоснование', 'Наименование работ и затрат', 'Ед. изм.', 'Количество'],
            ['1', 'ГЭСНм10-08-001-01', 'Извещатель пожарный дымовой', 'шт', 20],
            ['2', 'ТЦ_61.2.04.07_77', 'Извещатель пожарный дымовой ИП 212-141', 'шт', 20, 1500, 30000],
            ['3', 'ГЭСНм08-02-394-01', 'Устройство пожарной лестницы', 'шт', 1],
            [null, null, 'ВСЕГО по смете', 100000.0, null, 'ВСЕГО по смете'],
            [null, null, 'Всего - МР', 30000.0, null, 'Всего - МР'],
        ])]])->assertRedirect('/calculator/'.$estimate->id);

        // Работы сметы — две строки ГЭСН; материал (ТЦ_) в работы не попал.
        $this->assertSame(2, EstimateLine::query()->count());

        // По смете заказчика: 100 000 − 30 000 = 70 000 ₽; по нашим расценкам: 20 × 300 = 6 000 ₽.
        $this->actingAs($admin)->get('/calculator/'.$estimate->id)
            ->assertOk()
            ->assertSee('Школа № 5, АПС')
            ->assertSee('Калькулятор · Республика Марий Эл')
            ->assertSeeInOrder(['Работы по смете заказчика', "70\u{00A0}000\u{00A0}₽", 'По нашим расценкам', "6\u{00A0}000\u{00A0}₽"])
            ->assertDontSee('Сумма контракта')
            ->assertSeeInOrder(['Не посчитано (1)', 'Устройство пожарной лестницы', '+ в прайс']);

        $this->actingAs($admin)->get('/calculator')->assertOk()->assertSee('Школа № 5, АПС')->assertSee("6\u{00A0}000\u{00A0}₽");
        $this->actingAs($admin)->get('/calculator/'.$estimate->id.'/export')->assertOk();
        $this->actingAs($admin)->get('/calculator/'.$estimate->id.'/materials')->assertOk()->assertSee('Школа № 5, АПС');

        // «+ в прайс» из расчёта возвращает в расчёт.
        $this->actingAs($admin)->get('/prices/new?'.http_build_query(['name' => 'Устройство пожарной лестницы', 'unit' => 'шт', 'back' => $estimate->id]))
            ->assertOk()
            ->assertSee('/calculator/'.$estimate->id.'#unpriced', false);

        $this->actingAs($admin)->delete('/calculator/'.$estimate->id)->assertRedirect('/calculator');
        $this->assertSame(0, Estimate::query()->count());
        $this->assertSame(0, EstimateFile::query()->count());
    }

    public function test_technical_task_in_word_gives_draft_and_works_can_be_added_by_hand(): void
    {
        $admin = User::factory()->admin()->create();
        $estimate = Estimate::factory()->create(['contract_id' => null, 'name' => 'Троллеи', 'region_code' => '12']);

        $this->actingAs($admin)->post('/calculator/'.$estimate->id.'/files', ['files' => [$this->docx(TzDraftExtractorTest::PARAGRAPHS)]])
            ->assertRedirect('/calculator/'.$estimate->id);

        $this->assertSame(EstimateFileStatus::Parsed, EstimateFile::query()->sole()->status);
        $lines = EstimateLine::query()->with('priceItem')->orderBy('position')->get();
        // Демонтаж — только с позициями демонтажа, монтаж — с монтажом.
        $this->assertSame([
            'Демонтаж троллеев или шинопровода',
            'Демонтаж кабеля или провода',
            'Демонтаж рубильника',
            'Монтаж троллейного шинопровода',
            'Монтаж блока подвода питания шинопровода',
            'Монтаж токосъёмника',
            'Установка световой индикации напряжения',
            'Прокладка кабеля в коробе или кабель-канале',
            'Установка рубильника или ящика с рубильником',
        ], $lines->map(fn (EstimateLine $line): ?string => $line->priceItem?->name)->all());
        $busway = $lines->firstOrFail(fn (EstimateLine $line): bool => $line->name === 'Монтаж: Троллейный шинопровод');

        // Цена шинопровода вписана — работа посчитана: 114 м × 1 000 ₽.
        PriceItem::query()->where('name', 'Монтаж троллейного шинопровода')->update(['work_price_kopecks' => 100_000]);
        $this->actingAs($admin)->get('/calculator/'.$estimate->id)
            ->assertOk()
            ->assertSee('Монтаж: Троллейный шинопровод')
            ->assertSee("114\u{00A0}000\u{00A0}₽")
            ->assertSee('Добавить работу вручную');

        // Работа, которой нет в ТЗ, — вручную; позиция прайса выбрана сама по названию.
        PriceItem::query()->where('name', 'Испытания и измерения электроустановки')->update(['work_price_kopecks' => 2_500_000]);
        $this->actingAs($admin)->post('/estimates/'.$estimate->id.'/lines', ['name' => 'Испытания электрооборудования, измерение сопротивления изоляции', 'unit' => 'компл', 'quantity' => '1'])
            ->assertRedirect();
        $manual = EstimateLine::query()->whereNull('estimate_file_id')->sole();
        $this->assertSame('Испытания и измерения электроустановки', $manual->priceItem?->name);
        $this->assertSame(1_000, $manual->quantity_milli);

        // Объём черновика поправили: 114 → 96,5 м.
        $this->actingAs($admin)->put('/estimate-lines/'.$busway->id, ['price_item' => (string) $busway->price_item_id, 'quantity' => '96,5'])
            ->assertRedirect();
        $this->assertSame(96_500, $busway->fresh()?->quantity_milli);

        // 96,5 × 1 000 + 25 000 = 121 500 ₽.
        $this->actingAs($admin)->get('/calculator/'.$estimate->id)->assertOk()->assertSee("121\u{00A0}500\u{00A0}₽");

        $this->actingAs($admin)->post('/estimates/'.$estimate->id.'/lines', ['name' => '', 'unit' => 'кг', 'quantity' => '0'])
            ->assertSessionHasErrors(['name', 'unit', 'quantity']);
        $this->actingAs(User::factory()->create())->post('/estimates/'.$estimate->id.'/lines', ['name' => 'Чужая', 'unit' => 'шт', 'quantity' => '1'])
            ->assertForbidden();
    }

    public function test_validation_and_access(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/calculator', ['name' => '', 'region_code' => '99'])
            ->assertSessionHasErrors(['name', 'region_code']);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/calculator')->assertForbidden();
        $this->actingAs($user)->post('/calculator', ['name' => 'Чужой', 'region_code' => '12'])->assertForbidden();

        // Смета тендера через калькулятор не открывается.
        $tender = Estimate::factory()->create();
        $this->actingAs($admin)->get('/calculator/'.$tender->id)->assertNotFound();
        $this->actingAs($admin)->delete('/calculator/'.$tender->id)->assertNotFound();
        $this->assertNotNull(Estimate::query()->find($tender->id));
    }

    /**
     * Документ Word с абзацами — как ТЗ без таблицы объёмов.
     *
     * @param  list<string>  $paragraphs
     */
    private function docx(array $paragraphs): UploadedFile
    {
        $body = '';
        foreach ($paragraphs as $text) {
            $body .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p>';
        }
        $path = (string) tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'ТЗ.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
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
