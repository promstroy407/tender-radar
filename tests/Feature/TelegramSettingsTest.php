<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Telegram\TelegramConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '1234567890:AAH_abcdefghijklmnopqrstuvwxyz-0123';

    private const PROXY = 'https://tg.example.test/secret-path-abcdef0123456789';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://tender.example.test',
            'services.telegram' => [
                'bot_token' => null,
                'api_url' => null,
                'webhook_secret' => 'hook-secret',
                'updates_mode' => 'webhook',
            ],
        ]);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/settings/telegram')->assertRedirect('/login');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/telegram')->assertForbidden();
        $this->actingAs($user)->put('/settings/telegram', ['bot_token' => self::TOKEN])->assertForbidden();
        $this->assertFalse($this->settings()->has(SettingKey::TelegramBotToken));
    }

    public function test_admin_saves_token_and_url_encrypted(): void
    {
        $this->actingAs($this->admin())
            ->put('/settings/telegram', ['bot_token' => ' '.self::TOKEN.' ', 'api_url' => self::PROXY.'/'])
            ->assertRedirect('/settings/telegram')
            ->assertSessionHas('status');

        $this->assertSame(self::TOKEN, $this->settings()->get(SettingKey::TelegramBotToken));
        $this->assertSame(self::PROXY, $this->settings()->get(SettingKey::TelegramApiUrl));

        $raw = DB::table('settings')->pluck('value')->implode(' ');
        $this->assertStringNotContainsString(self::TOKEN, $raw);
        $this->assertStringNotContainsString('secret-path', $raw);
    }

    public function test_page_never_shows_saved_secrets(): void
    {
        $this->settings()->put(SettingKey::TelegramBotToken, self::TOKEN);
        $this->settings()->put(SettingKey::TelegramApiUrl, self::PROXY);

        $this->actingAs($this->admin())->get('/settings/telegram')
            ->assertOk()
            ->assertSee('задан здесь')
            ->assertDontSee(self::TOKEN)
            ->assertDontSee('secret-path');
    }

    public function test_empty_fields_keep_saved_values(): void
    {
        $this->settings()->put(SettingKey::TelegramBotToken, self::TOKEN);

        $this->actingAs($this->admin())->put('/settings/telegram', ['bot_token' => '', 'api_url' => '']);

        $this->assertSame(self::TOKEN, $this->settings()->get(SettingKey::TelegramBotToken));
    }

    public function test_clear_checkbox_removes_value(): void
    {
        $this->settings()->put(SettingKey::TelegramApiUrl, self::PROXY);

        $this->actingAs($this->admin())->put('/settings/telegram', ['clear_api_url' => '1']);

        $this->assertFalse($this->settings()->has(SettingKey::TelegramApiUrl));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put('/settings/telegram', ['bot_token' => 'not a token', 'api_url' => 'http://tg.example.test/x'])
            ->assertSessionHasErrors(['bot_token', 'api_url']);

        $this->assertFalse($this->settings()->has(SettingKey::TelegramBotToken));
        $this->assertFalse($this->settings()->has(SettingKey::TelegramApiUrl));
    }

    public function test_saved_values_override_env(): void
    {
        config(['services.telegram.bot_token' => '999:from-env', 'services.telegram.api_url' => 'https://env.example.test']);
        $this->settings()->put(SettingKey::TelegramBotToken, self::TOKEN);
        $this->settings()->put(SettingKey::TelegramApiUrl, self::PROXY);

        $config = $this->app->make(TelegramConfig::class);

        $this->assertSame(self::TOKEN, $config->botToken);
        $this->assertSame(self::PROXY, $config->apiUrl);
    }

    public function test_check_button_reports_bot_username(): void
    {
        $this->settings()->put(SettingKey::TelegramBotToken, self::TOKEN);
        $this->settings()->put(SettingKey::TelegramApiUrl, self::PROXY);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['username' => 'tender_radar_bot']])]);

        $this->actingAs($this->admin())->post('/settings/telegram/check')
            ->assertRedirect('/settings/telegram')
            ->assertSessionHas('status', 'Бот на связи: @tender_radar_bot');

        Http::assertSent(fn (Request $request): bool => $request->url() === self::PROXY.'/bot'.self::TOKEN.'/getMe');
    }

    public function test_check_without_token_shows_clear_error(): void
    {
        $this->actingAs($this->admin())->post('/settings/telegram/check')
            ->assertSessionHas('error', 'Не задан TELEGRAM_BOT_TOKEN: вставьте токен бота в «Настройки → Telegram».');
    }

    public function test_check_error_does_not_leak_secrets(): void
    {
        $this->settings()->put(SettingKey::TelegramBotToken, self::TOKEN);
        $this->settings()->put(SettingKey::TelegramApiUrl, self::PROXY);
        Http::fake(['*' => Http::failedConnection('timeout for '.self::PROXY.'/bot'.self::TOKEN.'/getMe')]);

        $response = $this->actingAs($this->admin())->post('/settings/telegram/check');

        $error = (string) session('error');
        $response->assertSessionHas('error');
        $this->assertStringNotContainsString(self::TOKEN, $error);
        $this->assertStringNotContainsString('secret-path', $error);
    }

    public function test_webhook_button_sets_webhook(): void
    {
        $this->settings()->put(SettingKey::TelegramBotToken, self::TOKEN);
        Http::fake([
            '*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
            '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => 'x', 'pending_update_count' => 0]]),
        ]);

        $this->actingAs($this->admin())->post('/settings/telegram/webhook')
            ->assertSessionHas('status');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/setWebhook')
            && $request['url'] === 'https://tender.example.test/telegram/webhook'
            && $request['secret_token'] === 'hook-secret');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function settings(): SettingsService
    {
        return $this->app->make(SettingsService::class);
    }
}
