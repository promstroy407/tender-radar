<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Jobs\FetchContractDocuments;
use App\Jobs\LoadDocumentsIntoEstimate;
use App\Jobs\ParseEstimateFile;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\EstimateFile;
use App\Models\User;
use App\Services\ContractService;
use App\Services\Eis\ContractDocumentsService;
use App\Services\SettingsService;
use App\Services\Sleeper;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Psr\Log\NullLogger;
use Tests\TestCase;
use ZipArchive;

class ContractDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'eis-token-1234567890-abcdef';

    private const URL = 'https://int.example.test/eis-integration/services/getDocsIP';

    private const NOTICE = '0108500000426005719';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.eis' => ['token' => null, 'url_44' => self::URL]]);
        $this->app->instance(Sleeper::class, new class extends Sleeper
        {
            public function sleep(int $seconds): void {}
        });
        $this->app->make(SettingsService::class)->put(SettingKey::EisToken, self::TOKEN);
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    public function test_fetch_saves_documentation_list_from_notice(): void
    {
        $contract = Contract::factory()->create(['notification_number' => self::NOTICE]);
        $this->fakeNotice($this->notice([
            ['Смета ЭОМ.xlsx', 'https://zakupki.example.test/file?uid=1', '15360', 'Сметная документация'],
            ['Проект.pdf', 'https://zakupki.example.test/file?uid=2', '', null],
            ['Битая ссылка.pdf', 'javascript:alert(1)', '10', null],
            // Тот же файл из изменения извещения — другая ссылка, то же имя и размер.
            ['Смета ЭОМ.xlsx', 'https://zakupki.example.test/file?uid=3', '15360', 'Сметная документация'],
        ]));

        $count = $this->app->make(ContractDocumentsService::class)->fetch($contract);

        $this->assertSame(2, $count);
        $documents = $contract->documents()->get();
        $this->assertSame(['Проект.pdf', 'Смета ЭОМ.xlsx'], $documents->pluck('name')->all());
        $smeta = $documents->firstWhere('name', 'Смета ЭОМ.xlsx');
        $this->assertInstanceOf(ContractDocument::class, $smeta);
        $this->assertSame(15360, $smeta->size_bytes);
        $this->assertSame('Сметная документация', $smeta->description);
        $this->assertNotNull($contract->refresh()->documents_checked_at);

        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && str_contains($request->body(), 'getDocsByReestrNumberRequest')
            && str_contains($request->body(), '<subsystemType>PRIZ</subsystemType><reestrNumber>'.self::NOTICE.'</reestrNumber>')
            && ! str_contains($request->body(), 'periodInfo'));
    }

    public function test_repeat_fetch_replaces_list_and_marks_empty_notice(): void
    {
        $contract = Contract::factory()->create(['notification_number' => self::NOTICE]);
        ContractDocument::query()->create(['contract_id' => $contract->id, 'name' => 'Старый.pdf', 'url' => 'https://old.test/1']);
        $this->fakeNotice($this->notice([]));

        $this->assertSame(0, $this->app->make(ContractDocumentsService::class)->fetch($contract));

        $this->assertSame(0, $contract->documents()->count());
        $this->assertNotNull($contract->refresh()->documents_checked_at);
    }

    public function test_feed_tender_is_counted_automatically_after_fetch(): void
    {
        Queue::fake([LoadDocumentsIntoEstimate::class]);
        $contract = Contract::factory()->create(['notification_number' => self::NOTICE, 'amount_kopecks' => 500_000_000]);
        $this->fakeNotice($this->notice([
            ['Смета ЭОМ.xlsx', 'https://zakupki.example.test/file?uid=1', '15360', null],
            ['Проект контракта.doc', 'https://zakupki.example.test/file?uid=2', '100', null],
        ]));

        $this->runFetch($contract);

        Queue::assertPushedOn('documents', LoadDocumentsIntoEstimate::class, fn (LoadDocumentsIntoEstimate $job): bool => $job->contractId === $contract->id && $job->userId === null);
    }

    public function test_no_auto_count_below_threshold(): void
    {
        Queue::fake([LoadDocumentsIntoEstimate::class]);
        $cheap = Contract::factory()->create(['notification_number' => self::NOTICE, 'amount_kopecks' => 100]);
        $this->fakeNotice($this->notice([['Смета ЭОМ.xlsx', 'https://zakupki.example.test/file?uid=1', '15360', null]]));

        $this->runFetch($cheap);

        Queue::assertNotPushed(LoadDocumentsIntoEstimate::class);
    }

    public function test_no_auto_count_without_estimate_works_list_or_project(): void
    {
        Queue::fake([LoadDocumentsIntoEstimate::class]);
        $paperOnly = Contract::factory()->create(['notification_number' => self::NOTICE, 'amount_kopecks' => 500_000_000]);
        $this->fakeNotice($this->notice([['Проект контракта.doc', 'https://zakupki.example.test/file?uid=2', '100', null]]));

        $this->runFetch($paperOnly);

        Queue::assertNotPushed(LoadDocumentsIntoEstimate::class);
    }

    public function test_card_shows_documents_and_buttons(): void
    {
        $admin = User::factory()->admin()->create();
        $contract = Contract::factory()->create(['notification_number' => self::NOTICE]);

        $this->actingAs($admin)->get('/tenders/'.$contract->id)
            ->assertOk()
            ->assertSee('Наш просчёт')
            ->assertSee('Найти документацию');

        ContractDocument::query()->create([
            'contract_id' => $contract->id, 'name' => 'Смета ЭОМ.xlsx', 'url' => 'https://zakupki.example.test/file?uid=1', 'size_bytes' => 2_621_440,
        ]);
        ContractDocument::query()->create([
            'contract_id' => $contract->id, 'name' => 'Проект контракта.doc', 'url' => 'https://zakupki.example.test/file?uid=2',
        ]);

        $this->actingAs($admin)->get('/tenders/'.$contract->id)
            ->assertOk()
            ->assertSeeInOrder(['Смета', 'Смета ЭОМ.xlsx', '2,5 МБ', 'Прочие файлы закупки (1)', 'Проект контракта.doc'])
            ->assertSee('Посчитать');
    }

    public function test_buttons_queue_jobs_and_are_admin_only(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $contract = Contract::factory()->create(['notification_number' => self::NOTICE]);

        $this->actingAs($admin)->post('/tenders/'.$contract->id.'/documents')->assertRedirect('/tenders/'.$contract->id);
        Queue::assertPushedOn('documents', FetchContractDocuments::class, fn (FetchContractDocuments $job): bool => $job->contractId === $contract->id);

        $this->actingAs($admin)->post('/tenders/'.$contract->id.'/documents/estimate')->assertRedirect('/tenders/'.$contract->id.'/estimate');
        Queue::assertPushedOn('documents', LoadDocumentsIntoEstimate::class, fn (LoadDocumentsIntoEstimate $job): bool => $job->contractId === $contract->id && $job->userId === $admin->id);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/tenders/'.$contract->id.'/documents')->assertForbidden();
        $this->actingAs($user)->post('/tenders/'.$contract->id.'/documents/estimate')->assertForbidden();
    }

    public function test_documents_are_downloaded_into_estimate_with_token(): void
    {
        Queue::fake([ParseEstimateFile::class]);
        $admin = User::factory()->admin()->create();
        $contract = Contract::factory()->create(['notification_number' => self::NOTICE]);
        ContractDocument::query()->create(['contract_id' => $contract->id, 'name' => 'Смета ЭОМ.csv', 'url' => 'https://zakupki.example.test/file?uid=1']);
        ContractDocument::query()->create(['contract_id' => $contract->id, 'name' => 'Огромный.pdf', 'url' => 'https://zakupki.example.test/file?uid=2', 'size_bytes' => 100 * 1024 * 1024]);
        Http::fake(['https://zakupki.example.test/*' => Http::response("Наименование;Ед;Кол-во\nКабель ВВГнг 3х2,5;м;120\n")]);

        (new LoadDocumentsIntoEstimate($contract->id, $admin->id))->handle(
            $this->app->make(ContractDocumentsService::class),
            new NullLogger,
        );

        $file = EstimateFile::query()->sole();
        $this->assertSame('Смета ЭОМ.csv', $file->original_name);

        // Повторное нажатие не добавляет тот же файл ещё раз.
        (new LoadDocumentsIntoEstimate($contract->id, $admin->id))->handle(
            $this->app->make(ContractDocumentsService::class),
            new NullLogger,
        );
        $this->assertSame(1, EstimateFile::query()->count());

        // Текст файла — так, как его видит разбор (для нечитаемых смет).
        $this->actingAs($admin)->get('/estimate-files/'.$file->id.'/text')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Кабель ВВГнг 3х2,5 | м | 120');
        $this->actingAs(User::factory()->create())->get('/estimate-files/'.$file->id.'/text')->assertForbidden();
        Queue::assertPushed(ParseEstimateFile::class, 1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('individualPerson_token', self::TOKEN));
        Http::assertSentCount(1);
    }

    private function runFetch(Contract $contract): void
    {
        (new FetchContractDocuments($contract->id))->handle(
            $this->app->make(ContractDocumentsService::class),
            $this->app->make(ContractService::class),
            $this->app->make(Dispatcher::class),
            new NullLogger,
        );
    }

    /**
     * @param  list<array{string, string, string, string|null}>  $files  имя, ссылка, размер, описание
     */
    private function notice(array $files): string
    {
        $items = '';
        foreach ($files as [$name, $url, $size, $description]) {
            $items .= '<ns4:attachmentInfo><ns4:publishedContentId>1</ns4:publishedContentId>'
                .'<ns4:fileName>'.htmlspecialchars($name).'</ns4:fileName>'
                .($size !== '' ? '<ns4:fileSize>'.$size.'</ns4:fileSize>' : '')
                .($description !== null ? '<ns4:docDescription>'.htmlspecialchars($description).'</ns4:docDescription>' : '')
                .'<ns4:url>'.htmlspecialchars($url).'</ns4:url></ns4:attachmentInfo>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><ns3:export xmlns:ns3="http://zakupki.gov.ru/oos/export/1" xmlns:ns4="http://zakupki.gov.ru/oos/common/1">'
            .'<ns3:epNotificationEF2020><commonInfo><purchaseNumber>'.self::NOTICE.'</purchaseNumber></commonInfo>'
            .'<attachmentsInfo>'.$items.'</attachmentsInfo></ns3:epNotificationEF2020></ns3:export>';
    }

    private function fakeNotice(string $xml): void
    {
        Http::fake([
            'https://archive.example.test/*' => Http::response($this->zip(['epNotificationEF2020_1.xml' => $xml])),
            self::URL => Http::response(
                '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
                .'<ns2:getDocsByReestrNumberResponse xmlns:ns2="http://zakupki.gov.ru/fz44/get-docs-ip/ws">'
                .'<dataInfo><archiveUrl>https://archive.example.test/notice.zip</archiveUrl></dataInfo>'
                .'</ns2:getDocsByReestrNumberResponse></soap:Body></soap:Envelope>',
            ),
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
