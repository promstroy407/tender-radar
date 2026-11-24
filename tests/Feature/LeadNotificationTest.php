<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SettingKey;
use App\Jobs\NotifyNewLead;
use App\Models\Deal;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Telegram\OwnerChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LeadNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:SECRET-bot-token';

    private const PROXY = 'https://tg.example.test/very-secret-path-123';

    private const LEADS_TOKEN = 'leads-token-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://tender.example.test',
            'services.leads.token' => self::LEADS_TOKEN,
            'services.telegram' => [
                'bot_token' => self::BOT_TOKEN,
                'api_url' => self::PROXY,
                'webhook_secret' => 'hook-secret',
                'updates_mode' => 'webhook',
            ],
        ]);
        Http::preventStrayRequests();
    }

    public function test_new_lead_notifies_owner_through_proxy(): void
    {
        $this->settings()->put(SettingKey::TelegramOwnerChatId, '777');
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);

        $id = $this->sendLead()->assertCreated()->json('deal_id');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($id): bool {
            $text = (string) $request['text'];

            return $request->url() === self::PROXY.'/bot'.self::BOT_TOKEN.'/sendMessage'
                && $request['chat_id'] === 777
                && str_contains($text, 'Новая заявка с сайта (es12-1042)')
                && str_contains($text, 'Иван, +79371124897')
                && str_contains($text, 'Объект: Квартира 60 м²')
                && str_contains($text, 'Калькулятор: от 85 000 ₽')
                && str_contains($text, 'Откуда узнали: Авито')
                && str_contains($text, 'Нужна замена проводки')
                && ! str_contains($text, 'Организация')
                && $request['reply_markup']['inline_keyboard'][0][0]['url'] === 'https://tender.example.test/deals/'.$id;
        });
    }

    public function test_repeat_lead_does_not_notify_again(): void
    {
        $this->settings()->put(SettingKey::TelegramOwnerChatId, '777');
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);

        $this->sendLead()->assertCreated();
        $this->sendLead()->assertOk();

        Http::assertSentCount(1);
    }

    public function test_without_owner_chat_nothing_is_sent(): void
    {
        Http::fake();

        $this->sendLead()->assertCreated();

        Http::assertNothingSent();
    }

    public function test_notification_is_queued_and_answer_does_not_wait_for_telegram(): void
    {
        Queue::fake();

        $id = $this->sendLead()->assertCreated()->json('deal_id');

        Queue::assertPushedOn('notify', NotifyNewLead::class, fn (NotifyNewLead $job): bool => $job->dealId === $id);
    }

    public function test_bot_not_configured_is_logged_not_failed(): void
    {
        config(['services.telegram.bot_token' => null]);
        $this->settings()->put(SettingKey::TelegramOwnerChatId, '777');
        Http::fake();

        $this->sendLead()->assertCreated();

        Http::assertNothingSent();
        $this->assertSame(1, Deal::query()->count());
    }

    public function test_long_message_is_shortened(): void
    {
        $this->settings()->put(SettingKey::TelegramOwnerChatId, '777');
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);

        $this->sendLead(['message' => str_repeat('я', 2000)])->assertCreated();

        Http::assertSent(fn (Request $request): bool => mb_strlen((string) $request['text']) < 800);
    }

    public function test_start_with_code_links_owner_chat_once(): void
    {
        $code = $this->linkCode();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);

        $this->postWebhook('/start '.$code, 555)->assertOk();

        $this->assertSame('555', $this->settings()->get(SettingKey::TelegramOwnerChatId));
        $this->assertNull($this->settings()->get(SettingKey::TelegramLinkCode));
        Http::assertSent(fn (Request $request): bool => $request['chat_id'] === 555
            && str_contains((string) $request['text'], 'уведомления о новых заявках'));

        // Код одноразовый: чужой чат с той же ссылкой не перехватит уведомления.
        $this->postWebhook('/start '.$code, 999)->assertOk();
        $this->assertSame('555', $this->settings()->get(SettingKey::TelegramOwnerChatId));
    }

    public function test_start_with_wrong_code_only_shows_app_button(): void
    {
        $this->linkCode();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []])]);

        $this->postWebhook('/start wrong-code', 555)->assertOk();

        $this->assertNull($this->settings()->get(SettingKey::TelegramOwnerChatId));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => isset($request['reply_markup']['inline_keyboard'][0][0]['web_app']));
    }

    public function test_settings_page_gives_one_time_link_and_unlinks(): void
    {
        $admin = User::factory()->admin()->create();
        Http::fake(['*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'RadarTendeMBot']])]);

        $this->actingAs($admin)->get('/settings/telegram')->assertOk()->assertSee('не включены');

        $this->actingAs($admin)->post('/settings/telegram/owner')
            ->assertRedirect('/settings/telegram')
            ->assertSessionHas('owner_link', fn (string $link): bool => preg_match('#^https://t\.me/RadarTendeMBot\?start=[0-9a-f]{24}$#', $link) === 1
                && str_ends_with($link, '='.$this->settings()->get(SettingKey::TelegramLinkCode)));

        $this->settings()->put(SettingKey::TelegramOwnerChatId, '555');
        $this->actingAs($admin)->get('/settings/telegram')->assertOk()->assertDontSee('не включены')->assertSee('Отключить');

        $this->actingAs($admin)->delete('/settings/telegram/owner')->assertRedirect('/settings/telegram');
        $this->assertNull($this->settings()->get(SettingKey::TelegramOwnerChatId));

        $this->actingAs(User::factory()->create())->post('/settings/telegram/owner')->assertForbidden();
        $this->actingAs(User::factory()->create())->delete('/settings/telegram/owner')->assertForbidden();
    }

    public function test_link_button_reports_bot_error(): void
    {
        Http::fake(['*' => Http::failedConnection('timeout')]);

        $this->actingAs(User::factory()->admin()->create())->post('/settings/telegram/owner')
            ->assertRedirect('/settings/telegram')
            ->assertSessionHas('error')
            ->assertSessionMissing('owner_link');
    }

    private function linkCode(): string
    {
        return $this->app->make(OwnerChatService::class)->newLinkCode();
    }

    /**
     * @return TestResponse<Response>
     */
    private function postWebhook(string $text, int $chatId): TestResponse
    {
        return $this->postJson('/telegram/webhook', [
            'update_id' => 1,
            'message' => ['message_id' => 1, 'chat' => ['id' => $chatId, 'type' => 'private'], 'text' => $text],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return TestResponse<Response>
     */
    private function sendLead(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/leads', array_merge([
            'external_id' => 'es12-1042',
            'received_at' => '2026-11-17T14:05:00+03:00',
            'name' => 'Иван',
            'phone' => '+79371124897',
            'facility' => 'Квартира 60 м²',
            'service' => 'Замена проводки',
            'origin' => 'Авито',
            'message' => 'Нужна замена проводки',
            'estimate' => 'от 85 000 ₽',
        ], $overrides), ['Authorization' => 'Bearer '.self::LEADS_TOKEN]);
    }

    private function settings(): SettingsService
    {
        return $this->app->make(SettingsService::class);
    }
}
