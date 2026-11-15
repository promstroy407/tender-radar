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

        // Работа: 20×300 + 150×30 + 1×1000 = 11 500 ₽; материалы: 15 000 + 9 000,50 + 50 000 = 74 000,50 ₽.
        $this->actingAs($this->admin())->get('/tenders/'.$contract->id.'/estimate')
            ->assertOk()
            ->assertSee("11\u{00A0}500\u{00A0}₽")
            ->assertSee("74\u{00A0}000,50\u{00A0}₽")
            ->assertSee("85\u{00A0}500,50\u{00A0}₽")
            ->assertSee('85,5 %')
            ->assertSee('не сопоставлено 1');

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id)
            ->assertOk()
            ->assertSee("85\u{00A0}500,50\u{00A0}₽");
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
        $this->assertSame(390_000, $line->workKopecks());

        $this->actingAs($this->admin())->put('/estimate-lines/'.$line->id, ['price_item' => 'skip']);
        $line->refresh();
        $this->assertSame(LineMatch::Skip, $line->match);
        $this->assertNull($line->workKopecks());

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

    public function test_scanned_pdf_gets_clear_message(): void
    {
        Process::fake(['*pdftotext*' => Process::result('')]);
        $contract = Contract::factory()->create();

        $this->actingAs($this->admin())->post('/tenders/'.$contract->id.'/estimate/files', [
            'files' => [UploadedFile::fake()->create('скан.pdf', 50, 'application/pdf')],
        ]);

        $this->assertStringContainsString('скан', (string) EstimateFile::query()->sole()->error);
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
        // 2,5 × 1 300 = 3 250 ₽ работы + 100,50 ₽ материала.
        $this->assertEqualsWithDelta(3350.5, $sheet->getCell('I6')->getCalculatedValue(), 0.001);
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
