<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Telegram\TelegramApiException;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramConfig;
use App\Services\Telegram\TelegramConfigException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:SECRET-bot-token';

    private const PROXY = 'https://tg.example.test/very-secret-path-123';

    private const WEBHOOK_SECRET = 'webhook-secret-xyz';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://tender.example.test',
            'services.telegram' => [
                'bot_token' => self::TOKEN,
                'api_url' => self::PROXY,
                'webhook_secret' => self::WEBHOOK_SECRET,
                'updates_mode' => 'webhook',
            ],
        ]);
    }

    public function test_url_goes_through_proxy(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['username' => 'tender_bot']])]);

        $this->bot()->call('getMe');

        Http::assertSent(fn (Request $request): bool => $request->url() === self::PROXY.'/bot'.self::TOKEN.'/getMe');
    }

    public function test_url_without_proxy_is_official_api(): void
    {
        config(['services.telegram.api_url' => '']);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);

        $this->bot()->call('getMe');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot'.self::TOKEN.'/getMe');
    }

    public function test_trailing_slash_in_proxy_is_ignored(): void
    {
        config(['services.telegram.api_url' => self::PROXY.'/']);

        $this->assertSame(self::PROXY.'/bot'.self::TOKEN.'/getMe', $this->bot()->methodUrl('getMe'));
    }

    public function test_connection_error_does_not_leak_secrets(): void
    {
        Http::fake(['*' => Http::failedConnection(
            'cURL error 28: Operation timed out for '.self::PROXY.'/bot'.self::TOKEN.'/sendMessage',
        )]);

        try {
            $this->bot()->call('sendMessage', ['chat_id' => 1, 'text' => 'x']);
            $this->fail('Ожидалась ошибка');
        } catch (TelegramApiException $e) {
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
            $this->assertStringNotContainsString('very-secret-path-123', $e->getMessage());
            $this->assertStringNotContainsString('tg.example.test', $e->getMessage());
            $this->assertStringContainsString('***', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_telegram_error_response_does_not_leak_secrets(): void
    {
        Http::fake(['*' => Http::response([
            'ok' => false,
            'description' => 'Unauthorized for token '.self::TOKEN,
        ], 401)]);

        try {
            $this->bot()->call('getMe');
            $this->fail('Ожидалась ошибка');
        } catch (TelegramApiException $e) {
            $this->assertStringNotContainsString(self::TOKEN, $e->getMessage());
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
        }
    }

    public function test_http_proxy_url_is_rejected_with_clear_message(): void
    {
        config(['services.telegram.api_url' => 'http://tg.example.test/path']);

        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('TELEGRAM_API_URL должен быть адресом вида https://');

        $this->app->make(TelegramConfig::class);
    }

    public function test_proxy_url_without_host_is_rejected(): void
    {
        config(['services.telegram.api_url' => 'https:///path']);

        $this->expectException(TelegramConfigException::class);

        $this->app->make(TelegramConfig::class);
    }

    public function test_missing_token_gives_clear_error(): void
    {
        config(['services.telegram.bot_token' => '']);

        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Не задан TELEGRAM_BOT_TOKEN');

        $this->bot()->call('getMe');
    }

    public function test_webhook_without_secret_is_forbidden(): void
    {
        Http::fake();

        $this->postJson('/telegram/webhook', $this->startUpdate())->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_webhook_with_wrong_secret_is_forbidden(): void
    {
        $this->postJson('/telegram/webhook', $this->startUpdate(), [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong',
        ])->assertForbidden();
    }

    public function test_webhook_is_forbidden_when_secret_not_configured(): void
    {
        config(['services.telegram.webhook_secret' => '']);

        $this->postJson('/telegram/webhook', $this->startUpdate(), [
            'X-Telegram-Bot-Api-Secret-Token' => '',
        ])->assertForbidden();
    }

    public function test_start_command_replies_with_mini_app_button(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);

        $this->postJson('/telegram/webhook', $this->startUpdate(), [
            'X-Telegram-Bot-Api-Secret-Token' => self::WEBHOOK_SECRET,
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/sendMessage')
                && $request['chat_id'] === 42
                && $request['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] === 'https://tender.example.test/';
        });
    }

    public function test_webhook_answers_200_even_if_telegram_is_down(): void
    {
        Http::fake(['*' => Http::response(['ok' => false, 'description' => 'Bad Gateway'], 502)]);

        $this->postJson('/telegram/webhook', $this->startUpdate(), [
            'X-Telegram-Bot-Api-Secret-Token' => self::WEBHOOK_SECRET,
        ])->assertOk();
    }

    public function test_set_webhook_command_sends_url_and_secret(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);

        $this->command('telegram:webhook', ['action' => 'set'])->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return str_ends_with($request->url(), '/setWebhook')
                && $request['url'] === 'https://tender.example.test/telegram/webhook'
                && $request['secret_token'] === self::WEBHOOK_SECRET;
        });
    }

    public function test_poll_does_nothing_in_webhook_mode(): void
    {
        Http::fake();

        $this->command('telegram:poll')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_poll_deletes_webhook_and_handles_updates(): void
    {
        config(['services.telegram.updates_mode' => 'polling']);
        Http::fake([
            '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
            '*/getUpdates' => Http::response(['ok' => true, 'result' => [
                ['update_id' => 7] + $this->startUpdate(),
            ]]),
            '*/sendMessage' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $this->command('telegram:poll')->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/deleteWebhook'));
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/getUpdates')
            && $request['timeout'] === 25 && $request['offset'] === 0);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sendMessage'));
        $this->assertSame(8, cache()->get('telegram:updates-offset'));
    }

    private function bot(): TelegramBotClient
    {
        return $this->app->make(TelegramBotClient::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function startUpdate(): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 42, 'type' => 'private'],
                'text' => '/start',
            ],
        ];
    }
}
