<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Models\Contract;
use App\Models\Region;
use App\Models\User;
use App\Services\KpTemplateService;
use App\Services\SettingsService;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KpTemplateAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_template_warns_about_missing_attachment(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->get('/template')
            ->assertOk()
            ->assertSee('Субподряд: электромонтаж и слаботочка на объекте {объект}')
            ->assertSee('PDF с КП не загружен');
    }

    public function test_template_is_saved_and_rendered_with_fields(): void
    {
        Storage::fake('local');
        $contract = Contract::factory()->hot()->create(['subject' => 'Ремонт школы 5', 'customer_name' => 'ГБУ «Школа 5»', 'amount_kopecks' => 123_456_789]);

        $this->actingAs($this->admin())->put('/template', [
            'subject' => ' КП для {победитель} ',
            'body' => "Объект {объект}\r\nСумма {сумма}\r\nЗаказчик {заказчик}, {регион}\r\nНеизвестное {поле}",
            'daily_limit' => '25',
        ])->assertRedirect('/template');

        $rendered = $this->app->make(KpTemplateService::class)->render($contract->load(['company', 'region']));

        $this->assertSame('КП для '.$contract->company->name, $rendered->subject);
        $this->assertStringContainsString("Объект Ремонт школы 5\nСумма 1\u{00A0}234\u{00A0}567,89\u{00A0}₽", $rendered->body);
        $this->assertStringContainsString('Заказчик ГБУ «Школа 5», '.$contract->region->name, $rendered->body);
        $this->assertStringContainsString('{поле}', $rendered->body);
        $this->assertSame('25', $this->app->make(SettingsService::class)->get(SettingKey::KpDailyLimit));
    }

    public function test_template_validation(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->put('/template', [
            'subject' => '',
            'body' => str_repeat('я', 10001),
            'daily_limit' => '0',
            'attachment' => UploadedFile::fake()->create('kp.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertSessionHasErrors(['subject', 'body', 'daily_limit', 'attachment']);
    }

    public function test_attachment_upload_and_removal(): void
    {
        Storage::fake('local');
        $data = ['subject' => 'Тема', 'body' => 'Текст во вложении', 'daily_limit' => 30];

        $this->actingAs($this->admin())->put('/template', $data + ['attachment' => UploadedFile::fake()->create('kp.pdf', 50, 'application/pdf')]);
        Storage::disk('local')->assertExists(KpTemplateService::ATTACHMENT_PATH);

        $this->actingAs($this->admin())->put('/template', $data + ['remove_attachment' => '1']);
        Storage::disk('local')->assertMissing(KpTemplateService::ATTACHMENT_PATH);
    }

    public function test_search_settings_toggle_regions_and_threshold_in_kopecks(): void
    {
        $this->seed(RegionSeeder::class);

        $this->actingAs($this->admin())->get('/settings')
            ->assertOk()
            ->assertSee('Республика Марий Эл')
            ->assertSee('1 000 000');

        $this->actingAs($this->admin())->put('/settings', [
            'regions' => ['12', '16'],
            'min_amount_rubles' => "2\u{00A0}500 000",
        ])->assertRedirect('/settings');

        $this->assertTrue(Region::query()->findOrFail('12')->is_enabled);
        $this->assertFalse(Region::query()->findOrFail('77')->is_enabled);
        $this->assertSame(250_000_000, Region::query()->findOrFail('77')->min_amount_kopecks);
    }

    public function test_search_settings_validation_and_access(): void
    {
        $this->seed(RegionSeeder::class);

        $this->actingAs($this->admin())->put('/settings', ['regions' => ['99'], 'min_amount_rubles' => '1,5 млн'])
            ->assertSessionHasErrors(['regions.0', 'min_amount_rubles']);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/settings')->assertForbidden();
        $this->actingAs($user)->get('/template')->assertForbidden();
        $this->actingAs($user)->put('/settings', ['min_amount_rubles' => '1'])->assertForbidden();
    }

    public function test_demo_data_load_and_remove(): void
    {
        $this->actingAs($this->admin())->post('/settings/demo')->assertRedirect('/');
        $this->assertSame(5, Contract::query()->where('registry_number', 'like', 'DEMO-%')->count());

        $this->actingAs($this->admin())->get('/')->assertSee('[Демо]');

        $this->actingAs($this->admin())->delete('/settings/demo')->assertRedirect('/settings');
        $this->assertSame(0, Contract::query()->count());
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
