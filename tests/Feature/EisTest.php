<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Jobs\RunEisProbe;
use App\Models\User;
use App\Services\Eis\EisClient;
use App\Services\Eis\EisException;
use App\Services\Eis\EisProbe;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class EisTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'eis-token-1234567890-abcdef';

    private const URL = 'https://int44.example.test/eis-integration/services/getDocsIP';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.eis' => ['token' => null, 'url_44' => self::URL]]);
        Storage::fake('local');
    }

    public function test_envelope_has_token_in_header_and_fields_in_schema_order(): void
    {
        $this->settings()->put(SettingKey::EisToken, self::TOKEN);

        $xml = $this->client()->envelope('getDocsByOrgRegionRequest', [
            'orgRegion' => '12', 'subsystemType' => 'RGK', 'documentType44' => 'contract',
        ], '2026-11-14');

        $this->assertStringContainsString('<individualPerson_token>'.self::TOKEN.'</individualPerson_token>', $xml);
        $this->assertStringContainsString('xmlns:ws="http://zakupki.gov.ru/fz44/get-docs-ip/ws"', $xml);
        $this->assertMatchesRegularExpression('#<orgRegion>12</orgRegion><subsystemType>RGK</subsystemType><documentType44>contract</documentType44><periodInfo><exactDate>2026-11-14</exactDate></periodInfo>#', $xml);
    }

    public function test_archive_urls_are_parsed(): void
    {
        $this->settings()->put(SettingKey::EisToken, self::TOKEN);
        Http::fake([self::URL => Http::response($this->response('<dataInfo><archiveUrl>https://a/1</archiveUrl><archiveUrl>https://a/2</archiveUrl></dataInfo>'))]);

        $this->assertSame(['https://a/1', 'https://a/2'], $this->client()->docsByOrgRegion('12', 'RGK', 'contract', '2026-11-14'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), self::TOKEN) && $request->url() === self::URL);
    }

    public function test_eis_error_is_reported_without_token(): void
    {
        $this->settings()->put(SettingKey::EisToken, self::TOKEN);
        Http::fake([self::URL => Http::response($this->response('<errorInfo><code>1</code><message>Токен '.self::TOKEN.' не найден</message></errorInfo>'), 200)]);

        try {
            $this->client()->docsByOrgRegion('12', 'RGK', 'contract', '2026-11-14');
            $this->fail('Ожидалась ошибка');
        } catch (EisException $e) {
            $this->assertStringContainsString('не найден', $e->getMessage());
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }
    }

    public function test_missing_token_gives_clear_error(): void
    {
        $this->expectException(EisException::class);
        $this->expectExceptionMessage('Не задан токен ЕИС');

        $this->client()->docsByOrgRegion('12', 'RGK', 'contract', '2026-11-14');
    }

    public function test_archive_download_sends_token_header(): void
    {
        $this->settings()->put(SettingKey::EisToken, self::TOKEN);
        Http::fake(['https://a/1' => Http::response('ZIP')]);

        $this->assertSame('ZIP', $this->client()->downloadArchive('https://a/1'));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('individualPerson_token', self::TOKEN));
    }

    public function test_probe_reads_schema_requests_contracts_and_saves_sample(): void
    {
        $this->settings()->put(SettingKey::EisToken, self::TOKEN);
        Http::fake([
            self::URL.'?xsd=getDocsIP-ws-api.xsd' => Http::response('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:include schemaLocation="getDocsIP?xsd=types.xsd"/><xs:simpleType name="subsystemType"><xs:restriction base="xs:string"><xs:enumeration value="PRIZ"/><xs:enumeration value="RGK"/></xs:restriction></xs:simpleType></xs:schema>'),
            self::URL.'?xsd=types.xsd' => Http::response('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:simpleType name="documentType44"><xs:restriction base="xs:string"><xs:enumeration value="epNotificationEF2020"/><xs:enumeration value="contract"/></xs:restriction></xs:simpleType></xs:schema>'),
            self::URL => Http::response($this->response('<dataInfo><archiveUrl>https://a/1</archiveUrl></dataInfo>')),
            'https://a/1' => Http::response($this->zip(['contract_1.xml' => '<contract><regNum>123</regNum></contract>'])),
        ]);

        $report = $this->app->make(EisProbe::class)->run('12', '2026-11-14');

        $this->assertTrue($report['ok']);
        $this->assertSame('contract', $report['document_type']);
        $this->assertSame(['PRIZ', 'RGK'], $report['schema']['subsystemType']);
        $this->assertSame(1, $report['archives']);
        $this->assertSame(['contract_1.xml'], $report['sample']['files']);
        $this->assertStringContainsString('<regNum>123</regNum>', $report['sample']['xml']);
        $this->assertSame($report, $this->app->make(EisProbe::class)->lastReport());
    }

    public function test_probe_uses_contract_type_when_schema_has_no_document_enum(): void
    {
        $this->settings()->put(SettingKey::EisToken, self::TOKEN);
        Http::fake([
            self::URL.'?xsd=getDocsIP-ws-api.xsd' => Http::response('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"><xs:element name="documentType44" type="documentType"/><xs:simpleType name="commonSubsystemType"><xs:restriction base="xs:string"><xs:enumeration value="RGK"/></xs:restriction></xs:simpleType></xs:schema>'),
            self::URL => Http::response($this->response('<dataInfo></dataInfo>')),
        ]);

        $report = $this->app->make(EisProbe::class)->run('12', '2026-11-14');

        $this->assertTrue($report['ok']);
        $this->assertSame('contract', $report['document_type']);
        $this->assertSame(0, $report['archives']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '<documentType44>contract</documentType44>'));
    }

    public function test_probe_reports_error_without_token(): void
    {
        Http::fake();

        $report = $this->app->make(EisProbe::class)->run();

        $this->assertFalse($report['ok']);
        $this->assertStringContainsString('Не задан токен ЕИС', $report['error']);
    }

    public function test_admin_saves_token_encrypted_and_page_hides_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/settings/eis', ['token' => ' '.self::TOKEN.' '])
            ->assertRedirect('/settings/eis');

        $this->assertSame(self::TOKEN, $this->settings()->get(SettingKey::EisToken));
        $this->assertStringNotContainsString(self::TOKEN, DB::table('settings')->pluck('value')->implode(' '));

        $this->actingAs($admin)->get('/settings/eis')
            ->assertOk()
            ->assertSee('задан здесь')
            ->assertDontSee(self::TOKEN);
    }

    public function test_token_with_spaces_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/settings/eis', ['token' => 'abc def ghi jkl'])
            ->assertSessionHasErrors('token');
    }

    public function test_non_admin_cannot_open_eis_settings(): void
    {
        $this->actingAs(User::factory()->create())->get('/settings/eis')->assertForbidden();
    }

    public function test_probe_button_queues_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/settings/eis/probe')
            ->assertRedirect('/settings/eis');

        Queue::assertPushed(RunEisProbe::class);
    }

    private function client(): EisClient
    {
        return $this->app->make(EisClient::class);
    }

    private function settings(): SettingsService
    {
        return $this->app->make(SettingsService::class);
    }

    private function response(string $inner): string
    {
        return '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<ns2:getDocsByOrgRegionResponse xmlns:ns2="http://zakupki.gov.ru/fz44/get-docs-ip/ws">'
            .$inner.'</ns2:getDocsByOrgRegionResponse></soap:Body></soap:Envelope>';
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
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
