<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContractKind;
use App\Enums\ProcurementLaw;
use App\Enums\SettingKey;
use App\Enums\WorkSection;
use App\Jobs\ImportEisDay;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\Region;
use App\Models\User;
use App\Services\Eis\EisImporter;
use App\Services\Eis\EisImportLog;
use App\Services\SettingsService;
use App\Services\Sleeper;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Unit\ContractXmlParserTest;
use ZipArchive;

class EisImportTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'eis-token-1234567890-abcdef';

    private const URL = 'https://int.example.test/eis-integration/services/getDocsIP';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.eis' => ['token' => null, 'url_44' => self::URL]]);
        $this->app->instance(Sleeper::class, new class extends Sleeper
        {
            public function sleep(int $seconds): void {}
        });
        Http::preventStrayRequests();
        Region::factory()->create(['code' => '12', 'name' => 'Республика Марий Эл']);
    }

    public function test_day_import_saves_our_contracts_with_winners(): void
    {
        $this->app->make(SettingsService::class)->put(SettingKey::EisToken, self::TOKEN);
        $this->fakeEis([
            'contract_1.xml' => $this->contract('1001', 'Выполнение работ по замене электропроводки', '2500000.50', '43.21.10.290', '1215000001'),
            'contract_2.xml' => $this->contract('1002', 'Поставка лекарственного препарата', '43267.00', '21.20.10.132', '1215000002'),
            'contract_3.xml' => $this->contract('1003', 'Капитальный ремонт здания школы', '15000000.00', '41.20.40.900', '1215000003'),
            'readme.txt' => 'не XML',
        ]);

        (new ImportEisDay('12', '2026-11-20'))->handle(
            $this->app->make(EisImporter::class),
            $this->app->make(EisImportLog::class),
        );

        $this->assertSame(2, Contract::query()->count());

        $electric = Contract::query()->where('registry_number', '1001')->sole();
        $this->assertSame(ProcurementLaw::Fz44, $electric->law);
        $this->assertSame(ContractKind::Profile, $electric->kind);
        $this->assertSame('12', $electric->region_code);
        $this->assertSame(250_000_050, $electric->amount_kopecks);
        $this->assertSame('2026-11-20', $electric->signed_at->toDateString());
        $this->assertEquals([WorkSection::Eom], $electric->sections->all());
        $this->assertSame('ГБОУ «ШКОЛА № 1» (ВЫМЫШЛЕННЫЙ ЗАКАЗЧИК)', $electric->customer_name);

        $winner = $electric->company;
        $this->assertSame('1215000001', $winner->inn);
        $this->assertSame('ООО "ПРИМЕР-МОНТАЖ"', $winner->name);
        $this->assertSame('info@primer-montazh.test', $winner->email);
        $this->assertSame('+79000000001', $winner->phone);
        $this->assertSame('Йошкар-Ола', $winner->city);

        $this->assertSame(ContractKind::General, Contract::query()->where('registry_number', '1003')->sole()->kind);

        $log = $this->app->make(EisImportLog::class)->entries();
        $this->assertSame(['region' => '12', 'date' => '2026-11-20', 'archives' => 1, 'files' => 3, 'matched' => 2, 'saved' => 2, 'error' => null], array_diff_key($log[0], ['finished_at' => true]));

        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && str_contains($request->body(), '<orgRegion>12</orgRegion><subsystemType>RGK</subsystemType><documentType44>contract</documentType44>')
            && str_contains($request->body(), '<exactDate>2026-11-20</exactDate>'));
    }

    public function test_repeat_import_updates_without_duplicates_and_keeps_contacts(): void
    {
        $this->app->make(SettingsService::class)->put(SettingKey::EisToken, self::TOKEN);
        Company::factory()->create(['inn' => '1215000001', 'email' => 'boss@found.test', 'phone' => '+79001112233']);
        $this->fakeEis([
            'contract_1.xml' => $this->contract('1001', 'Выполнение работ по замене электропроводки', '2500000.50', '43.21.10.290', '1215000001'),
        ]);

        $this->command('eis:import', ['--days' => 1, '--until' => '2026-11-20'])->assertSuccessful();
        $this->command('eis:import', ['--days' => 1, '--until' => '2026-11-20'])->assertSuccessful();

        $this->assertSame(1, Contract::query()->count());
        $this->assertSame(1, Company::query()->count());
        $company = Company::query()->sole();
        $this->assertSame('boss@found.test', $company->email);
        $this->assertSame('+79001112233', $company->phone);
        $this->assertSame('ООО "ПРИМЕР-МОНТАЖ"', $company->name);
    }

    public function test_contract_that_no_longer_matches_is_removed_unless_in_work(): void
    {
        $this->app->make(SettingsService::class)->put(SettingKey::EisToken, self::TOKEN);
        $stale = Contract::factory()->create(['law' => ProcurementLaw::Fz44, 'registry_number' => '2001', 'region_code' => '12']);
        $inWork = Contract::factory()->create(['law' => ProcurementLaw::Fz44, 'registry_number' => '2002', 'region_code' => '12']);
        Deal::factory()->tender()->create(['contract_id' => $inWork->id]);
        $this->fakeEis([
            'contract_1.xml' => $this->contract('2001', 'Поставка красок для ремонта помещений', '2000000.00', '20.30.12.000', '1215000001'),
            'contract_2.xml' => $this->contract('2002', 'Поставка красок для ремонта помещений', '2000000.00', '20.30.12.000', '1215000002'),
        ]);

        $this->command('eis:import', ['--days' => 1, '--until' => '2026-11-20'])->assertSuccessful();

        $this->assertNull(Contract::query()->find($stale->id));
        $this->assertNotNull(Contract::query()->find($inWork->id));
    }

    public function test_missing_token_is_logged(): void
    {
        Http::fake();

        $this->command('eis:import', ['--days' => 1, '--until' => '2026-11-20'])->assertSuccessful();

        $log = $this->app->make(EisImportLog::class)->entries();
        $this->assertCount(1, $log);
        $this->assertNotNull($log[0]['error']);
        Http::assertNothingSent();
    }

    public function test_planner_queues_enabled_regions_for_each_day(): void
    {
        Queue::fake();
        Region::factory()->create(['code' => '16']);
        Region::factory()->create(['code' => '21', 'is_enabled' => false]);

        $this->command('eis:import', ['--days' => 2, '--until' => '2026-11-20'])
            ->expectsOutputToContain('Поставлено заданий: 4')
            ->assertSuccessful();

        $pushed = [];
        Queue::assertPushed(ImportEisDay::class, function (ImportEisDay $job) use (&$pushed): bool {
            $pushed[] = $job->regionCode.' '.$job->date;

            return true;
        });
        sort($pushed);
        $this->assertSame(['12 2026-11-19', '12 2026-11-20', '16 2026-11-19', '16 2026-11-20'], $pushed);

        $this->command('eis:import', ['--until' => '20.11.2026'])->assertFailed();
    }

    public function test_settings_page_shows_log_and_starts_backfill(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-11-23 10:00:00');
        $admin = User::factory()->admin()->create();
        $this->app->make(EisImportLog::class)->recordError('12', Carbon::parse('2026-11-20'), 'ЕИС недоступен: сбой');

        $this->actingAs($admin)->get('/settings/eis')
            ->assertOk()
            ->assertSee('Сбор контрактов')
            ->assertSee('Республика Марий Эл')
            ->assertSee('ЕИС недоступен: сбой');

        $this->actingAs($admin)->post('/settings/eis/import')->assertRedirect('/settings/eis');
        Queue::assertPushed(ImportEisDay::class, 30);
        Queue::assertPushed(ImportEisDay::class, fn (ImportEisDay $job): bool => $job->date === '2026-11-22');

        $this->actingAs(User::factory()->create())->post('/settings/eis/import')->assertForbidden();
        Carbon::setTestNow();
    }

    public function test_import_is_scheduled_every_morning(): void
    {
        $events = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, 'eis:import'),
        ));

        $this->assertCount(1, $events);
        $this->assertSame('41 5 * * *', $events[0]->expression);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function fakeEis(array $files): void
    {
        Http::fake([
            'https://archive.example.test/*' => Http::response($this->zip($files)),
            self::URL => Http::response(
                '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                .'<ns2:getDocsByOrgRegionResponse xmlns:ns2="http://zakupki.gov.ru/fz44/get-docs-ip/ws">'
                .'<dataInfo><archiveUrl>https://archive.example.test/1.zip</archiveUrl></dataInfo>'
                .'</ns2:getDocsByOrgRegionResponse></soap:Body></soap:Envelope>',
            ),
        ]);
    }

    private function contract(string $regNum, string $subject, string $price, string $okpd2, string $inn): string
    {
        return strtr(ContractXmlParserTest::xml(), [
            '2121504059826000350' => $regNum,
            'Выполнение работ по замене электропроводки' => $subject,
            'Замена электропроводки' => $subject,
            '2500000.50' => $price,
            '43.21.10.290' => $okpd2,
            '1215000001' => $inn,
        ]);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
