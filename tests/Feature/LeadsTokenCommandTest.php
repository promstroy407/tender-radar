<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadsTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Своя копия .env во временной папке — настоящий файл не трогаем.
        $this->dir = sys_get_temp_dir().'/leads-token-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir.'/.env', "APP_NAME=Test\nLEADS_API_TOKEN=\n");
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/.env');
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_creates_token_once_and_shows_it(): void
    {
        $this->command('leads:token')
            ->expectsOutputToContain('показывается здесь')
            ->assertSuccessful();

        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^LEADS_API_TOKEN=[0-9a-f]{64}$/m', $env);
        $this->assertSame(1, substr_count($env, 'LEADS_API_TOKEN='));
        $this->assertStringContainsString('APP_NAME=Test', $env);

        // Повторный запуск не меняет токен и не показывает его.
        $this->command('leads:token')->expectsOutputToContain('Токен уже задан')->assertSuccessful();
        $this->assertSame($env, file_get_contents($this->dir.'/.env'));
    }

    public function test_force_replaces_token(): void
    {
        $this->command('leads:token')->assertSuccessful();
        $before = (string) file_get_contents($this->dir.'/.env');

        $this->command('leads:token', ['--force' => true])->assertSuccessful();
        $after = (string) file_get_contents($this->dir.'/.env');

        $this->assertNotSame($before, $after);
        $this->assertSame(1, substr_count($after, 'LEADS_API_TOKEN='));
    }

    public function test_appends_line_when_missing(): void
    {
        file_put_contents($this->dir.'/.env', 'APP_NAME=Test');

        $this->command('leads:token')->assertSuccessful();

        $this->assertMatchesRegularExpression('/^APP_NAME=Test\nLEADS_API_TOKEN=[0-9a-f]{64}\n$/', (string) file_get_contents($this->dir.'/.env'));
    }

    public function test_token_is_sent_to_owner_telegram_not_console(): void
    {
        config(['services.telegram' => [
            'bot_token' => '123456:SECRET-bot-token',
            'api_url' => 'https://tg.example.test/secret-path',
            'webhook_secret' => null,
            'updates_mode' => 'polling',
        ]]);
        $this->app->make(SettingsService::class)->put(SettingKey::TelegramOwnerChatId, '777');
        Http::preventStrayRequests();
        Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => []])]);

        $this->command('leads:token')
            ->expectsOutputToContain('отправлен вам в Telegram')
            ->doesntExpectOutputToContain('показывается здесь')
            ->assertSuccessful();

        preg_match('/^LEADS_API_TOKEN=([0-9a-f]{64})$/m', (string) file_get_contents($this->dir.'/.env'), $match);
        $token = $match[1] ?? 'missing';

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === 777 && $request['text'] === $token);
    }

    public function test_telegram_failure_is_reported(): void
    {
        config(['services.telegram' => [
            'bot_token' => '123456:SECRET-bot-token',
            'api_url' => 'https://tg.example.test/secret-path',
            'webhook_secret' => null,
            'updates_mode' => 'polling',
        ]]);
        $this->app->make(SettingsService::class)->put(SettingKey::TelegramOwnerChatId, '777');
        Http::fake(['*' => Http::failedConnection('timeout')]);

        $this->command('leads:token')->expectsOutputToContain('в Telegram не отправлен')->assertFailed();
    }

    public function test_missing_env_file_fails(): void
    {
        unlink($this->dir.'/.env');

        $this->command('leads:token')->expectsOutputToContain('.env не найден')->assertFailed();
    }
}
