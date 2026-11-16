<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstimateFileStatus;
use App\Enums\LineMatch;
use App\Jobs\SyncNormatives;
use App\Models\Contract;
use App\Models\EstimateLine;
use App\Models\GesnNorm;
use App\Models\LaborRate;
use App\Models\PriceItem;
use App\Models\User;
use App\Services\Estimates\EstimateCalculator;
use App\Services\Normatives\NormativePricer;
use App\Services\Normatives\NormativesSync;
use App\Services\Normatives\NormTableParser;
use Database\Seeders\PriceItemSeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class NormativesTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '9c9ae1a6-0a35-43c6-a2e6-072c3c0e1c0d';

    protected function setUp(): void
    {
        parent::setUp();

        config(['normatives.request_pause_ms' => 0, 'normatives.fgis_url' => 'https://fgis.test/api/']);
        Sleep::fake();
        $this->seed(RegionSeeder::class);
    }

    public function test_table_parser_reads_average_and_per_grade_labor(): void
    {
        $node = [
            'name' => 'Таблица ГЭСНм 08-03-609 Светильники светодиодные накладные и подвесные',
            'normTableJson' => json_encode([
                ['Number' => '08-03-609-01', 'Name' => 'Светильник линейный', 'MeterName' => '100 шт'],
                ['Number' => '08-02-147-01', 'Name' => 'Кабель по лоткам', 'MeterName' => '100 м'],
            ]),
            'normTableValueTableJson' => json_encode([
                ['Cipher' => '1', 'Name' => 'ЗАТРАТЫ ТРУДА РАБОЧИХ', 'NormTablePartNormValueList' => []],
                ['Cipher' => '2-100-02', 'NormTablePartNormValueList' => [['NormNumber' => '08-03-609-01', 'Value' => '10.5']]],
                ['Cipher' => '2-100-04', 'NormTablePartNormValueList' => [['NormNumber' => '08-03-609-01', 'Value' => '20']]],
                ['Cipher' => '1-100-38', 'NormTablePartNormValueList' => [['NormNumber' => '08-02-147-01', 'Value' => '9.28']]],
                ['Cipher' => '2', 'Name' => 'Затраты труда машинистов', 'NormTablePartNormValueList' => [['NormNumber' => '08-02-147-01', 'Value' => '0.01']]],
            ]),
        ];

        $norms = $this->app->make(NormTableParser::class)->parse($node);

        $this->assertSame('Светильники светодиодные накладные и подвесные', $norms[0]['table_name']);
        $this->assertSame([['code' => '2-100-02', 'milli' => 10_500], ['code' => '2-100-04', 'milli' => 20_000]], $norms[0]['labor']);
        $this->assertSame([['code' => '1-100-38', 'milli' => 9_280]], $norms[1]['labor']);
    }

    public function test_sync_loads_norms_and_rates_from_fgis(): void
    {
        $this->fakeFgis();

        $status = $this->app->make(NormativesSync::class)->run();

        $this->assertTrue($status['ok'], (string) ($status['error'] ?? ''));
        $this->assertSame(2, $status['norms']);
        $this->assertSame('3 квартал 2026 г.', $status['periods']['12']);
        $this->assertSame([['code' => '1-100-38', 'milli' => 9_280]], GesnNorm::query()->findOrFail('08-02-147-01')->labor);
        $this->assertSame('communication', GesnNorm::query()->findOrFail('10-08-002-02')->kind);
        $this->assertSame(34_640, LaborRate::query()->where('region_code', '12')->where('code', '1-100-38')->value('rate_kopecks'));
        $this->assertSame(5, LaborRate::query()->where('code', '1-100-38')->count());
    }

    public function test_sync_failure_is_reported_and_keeps_old_data(): void
    {
        GesnNorm::query()->create(['code' => '08-02-147-01', 'kind' => 'electric', 'table_name' => 'т', 'name' => 'н', 'unit' => '100 м', 'labor' => [['code' => '1-100-38', 'milli' => 9280]]]);
        Http::fake(['*' => Http::response('', 503)]);

        $status = $this->app->make(NormativesSync::class)->run();

        $this->assertFalse($status['ok']);
        $this->assertStringContainsString('ФГИС ЦС', $status['error']);
        $this->assertSame(1, GesnNorm::query()->count());
        $this->assertSame($status, $this->app->make(NormativesSync::class)->lastStatus());
    }

    public function test_normative_price_matches_formula(): void
    {
        // Марий Эл, 3 кв. 2026: 9,28 чел.-ч на 100 м × 346,40 ₽ × (1 + 97 % + 51 %) / 100 м = 79,72 ₽/м.
        $this->norm('08-02-147-01', '100 м', [['code' => '1-100-38', 'milli' => 9_280]]);
        $this->rate('12', '1-100-38', 34_640);
        $item = PriceItem::factory()->create(['unit' => 'м', 'gesn_code' => '08-02-147-01', 'work_price_kopecks' => 1]);

        $price = $this->app->make(NormativePricer::class)->price($item, '12');

        $this->assertNotNull($price);
        $this->assertSame(7_972, $price->kopecksPerUnit);
        $this->assertSame(97, $price->overheadPercent);
        $this->assertSame('3 квартал 2026 г.', $price->period);
        // Нет ставок региона — нормативной цены нет (останется ручная).
        $this->assertNull($this->app->make(NormativePricer::class)->price($item, '77'));
    }

    public function test_incompatible_units_give_no_price(): void
    {
        $this->norm('08-02-395-01', 'т', [['code' => '1-100-38', 'milli' => 54_000]]);
        $this->rate('12', '1-100-38', 34_640);
        $item = PriceItem::factory()->create(['unit' => 'м', 'gesn_code' => '08-02-395-01']);

        $this->assertNull($this->app->make(NormativePricer::class)->price($item, '12'));
    }

    public function test_estimate_lines_are_priced_by_tender_region(): void
    {
        $this->norm('10-08-002-02', 'шт', [['code' => '1-100-38', 'milli' => 1_680]], 'communication');
        $this->rate('12', '1-100-38', 34_640);
        $this->rate('77', '1-100-38', 72_354);
        $item = PriceItem::factory()->create(['unit' => 'шт', 'gesn_code' => '10-08-002-02']);

        foreach (['12' => 1_373_410, '77' => 2_868_690] as $region => $expected) {
            $contract = Contract::factory()->create(['region_code' => $region]);
            $line = EstimateLine::factory()->create(['quantity_milli' => 10_000, 'price_item_id' => $item->id, 'match' => LineMatch::Auto]);
            $estimate = $line->file->estimate;
            $estimate->update(['contract_id' => $contract->id]);
            $line->file->update(['status' => EstimateFileStatus::Parsed]);

            // 1,68 чел.-ч × ставка × (1 + 90 % + 46 %) = 1 373,41 ₽ (Марий Эл) / 2 868,69 ₽ (Москва) за шт × 10 шт.
            $summary = $this->app->make(EstimateCalculator::class)->summarize($estimate->fresh() ?? $estimate, $estimate->lines()->with('priceItem')->get());
            $this->assertSame($expected, $summary->priceListKopecks, 'регион '.$region);
        }
    }

    public function test_settings_show_status_and_button_queues_sync(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/settings')->assertOk()->assertSee('Ещё не загружались');

        Queue::fake();
        $this->actingAs($admin)->post('/settings/normatives')->assertRedirect('/settings');
        Queue::assertPushed(SyncNormatives::class);

        $this->actingAs(User::factory()->create())->post('/settings/normatives')->assertForbidden();
    }

    public function test_command_skips_fresh_normatives(): void
    {
        $this->fakeFgis();
        $this->command('normatives:sync', ['--now' => true])->assertSuccessful();
        $sent = Http::recorded()->count();

        $this->command('normatives:sync', ['--if-stale' => 7])->expectsOutputToContain('свежие')->assertSuccessful();
        $this->assertSame($sent, Http::recorded()->count());
    }

    public function test_price_list_shows_region_prices_and_saves_norm_code(): void
    {
        $this->seed(PriceItemSeeder::class);
        $this->norm('08-02-147-01', '100 м', [['code' => '1-100-38', 'milli' => 9_280]]);
        $this->rate('12', '1-100-38', 34_640);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/prices')
            ->assertOk()
            ->assertSee('ГЭСН 08-02-147-01')
            ->assertSee("79,72\u{00A0}₽/м", false);

        $item = PriceItem::query()->where('name', 'Монтаж кабельного лотка')->sole();
        $this->actingAs($admin)->put('/prices/'.$item->id, [
            'section' => 'electric', 'name' => $item->name, 'unit' => 'м', 'price_rubles' => '',
            'gesn_code' => '08-02-395-01', 'keywords' => 'лоток', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('08-02-395-01', $item->fresh()?->gesn_code);

        $this->actingAs($admin)->put('/prices/'.$item->id, [
            'section' => 'electric', 'name' => $item->name, 'unit' => 'м', 'gesn_code' => 'ГЭСН 8', 'keywords' => 'лоток',
        ])->assertSessionHasErrors('gesn_code');
    }

    /**
     * @param  list<array{code: string, milli: int}>  $labor
     */
    private function norm(string $code, string $unit, array $labor, string $kind = 'electric'): void
    {
        GesnNorm::query()->create(['code' => $code, 'kind' => $kind, 'table_name' => 'Таблица', 'name' => 'Норма '.$code, 'unit' => $unit, 'labor' => $labor]);
    }

    private function rate(string $region, string $code, int $kopecks): void
    {
        LaborRate::query()->create(['region_code' => $region, 'code' => $code, 'rate_kopecks' => $kopecks, 'period' => '3 квартал 2026 г.']);
    }

    /** Ответы ФГИС ЦС: два сборника по одной таблице и ставки для пяти регионов. */
    private function fakeFgis(): void
    {
        $table = fn (string $code, string $unit, string $cipher, string $value): array => [
            'guid' => 't-'.$code, 'isLeaf' => true, 'name' => 'Таблица ГЭСНм '.substr($code, 0, 9).' Тест',
            'normTableJson' => json_encode([['Number' => $code, 'Name' => 'Норма '.$code, 'MeterName' => $unit]]),
            'normTableValueTableJson' => json_encode([['Cipher' => $cipher, 'NormTablePartNormValueList' => [['NormNumber' => $code, 'Value' => $value]]]]),
        ];
        $children = [
            self::ROOT => [
                ['guid' => 'c8', 'isLeaf' => false, 'name' => 'Сборник 8. Электротехнические установки'],
                ['guid' => 'c10', 'isLeaf' => false, 'name' => 'Сборник 10. Оборудование связи'],
            ],
            'c8' => [['guid' => 's8', 'isLeaf' => false, 'name' => 'Раздел 6']],
            's8' => [$table('08-02-147-01', '100 м', '1-100-38', '9.28')],
            'c10' => [$table('10-08-002-02', 'шт', '1-100-38', '1.68')],
        ];

        Http::fake(function (Request $request) use ($children) {
            $url = $request->url();
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

            return match (true) {
                str_contains($url, 'NormLegalDocPublished') => Http::response([(string) $q['parentGuid'] => $children[(string) $q['parentGuid']] ?? []]),
                str_contains($url, 'PriceZones') => Http::response([['id' => 1000 + (int) $q['subjectId'], 'name' => 'Зона']]),
                str_contains($url, 'Periods') => Http::response([['id' => 427, 'name' => '3 квартал 2026 г.'], ['id' => 426, 'name' => '2 квартал 2026 г.']]),
                str_contains($url, 'RimWorkerSalaryRegistry') => Http::response(['total' => 2, 'items' => [
                    ['code' => '1-100-38', 'salary' => '346.40'],
                    ['code' => '2-100-04', 'salary' => '330.10'],
                ]]),
                default => Http::response('', 404),
            };
        });
    }
}
