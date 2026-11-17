<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LeadsApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-for-leads-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.leads.token' => self::TOKEN]);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_lead_creates_site_deal_with_trimmed_fields(): void
    {
        $response = $this->sendLead([
            'name' => '  Иван Сидоров ',
            'email' => '',
            'organization' => '   ',
            'message' => " Нужна замена проводки \n",
        ]);

        $response->assertCreated()->assertExactJson(['deal_id' => Deal::query()->sole()->id]);

        $deal = Deal::query()->sole();
        $this->assertSame(DealSource::Site, $deal->source);
        $this->assertSame(DealStatus::New, $deal->status);
        $this->assertSame(DealStatus::New, $deal->reached_status);
        $this->assertSame('es12-1042', $deal->external_id);
        $this->assertSame('Иван Сидоров', $deal->name);
        $this->assertSame('+79371124897', $deal->phone);
        $this->assertNull($deal->email);
        $this->assertNull($deal->organization);
        $this->assertSame('Нужна замена проводки', $deal->message);
        $this->assertSame('Яндекс Карты', $deal->origin);
        $this->assertSame('от 85 000 ₽', $deal->estimate);
        $this->assertSame('https://es12.ru/ceny?utm_source=yandex', $deal->source_url);
        // 14:05 по Москве = 11:05 UTC.
        $this->assertSame('2026-11-17 11:05:00', $deal->received_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_repeat_external_id_returns_200_and_does_not_duplicate(): void
    {
        $first = $this->sendLead()->assertCreated();
        $second = $this->sendLead(['name' => 'Другое имя'])->assertOk();

        $this->assertSame($first->json('deal_id'), $second->json('deal_id'));
        $this->assertSame(1, Deal::query()->count());
        $this->assertSame('Иван', Deal::query()->sole()->name);
    }

    public function test_missing_or_wrong_token_gives_401(): void
    {
        $this->postJson('/api/leads', $this->payload())->assertUnauthorized();
        $this->postJson('/api/leads', $this->payload(), ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
        $this->postJson('/api/leads', $this->payload(), ['Authorization' => self::TOKEN])->assertUnauthorized();

        config(['services.leads.token' => null]);
        $this->sendLead()->assertUnauthorized();
        config(['services.leads.token' => '']);
        $this->postJson('/api/leads', $this->payload(), ['Authorization' => 'Bearer '])->assertUnauthorized();

        $this->assertSame(0, Deal::query()->count());
    }

    public function test_validation_errors_give_422(): void
    {
        $this->sendLead([
            'external_id' => 'site-15',
            'received_at' => '2026-11-17 14:05:00',
            'name' => str_repeat('я', 101),
            'phone' => '89371124897',
            'email' => 'не почта',
            'organization' => str_repeat('я', 151),
            'facility' => str_repeat('я', 201),
            'service' => str_repeat('я', 201),
            'origin' => str_repeat('я', 51),
            'message' => str_repeat('я', 2001),
            'estimate' => str_repeat('я', 501),
            'source_url' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'external_id', 'received_at', 'name', 'phone', 'email', 'organization',
            'facility', 'service', 'origin', 'message', 'estimate', 'source_url',
        ]);

        $this->sendLead(['external_id' => '  ', 'name' => '', 'phone' => null, 'received_at' => '2026-11-17T14:05:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id', 'name', 'phone', 'received_at']);

        $this->sendLead(['name' => ['Иван'], 'received_at' => '2026-02-30T10:00:00+03:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'received_at']);

        $this->assertSame(0, Deal::query()->count());
    }

    public function test_received_at_accepts_utc_z_and_fractions(): void
    {
        $this->sendLead(['external_id' => 'es12-1', 'received_at' => '2026-11-17T11:05:00Z'])->assertCreated();
        $this->sendLead(['external_id' => 'es12-2', 'received_at' => '2026-11-17T11:05:00.123+00:00'])->assertCreated();

        $this->assertSame(2, Deal::query()->count());
    }

    public function test_more_than_30_requests_per_minute_gives_429(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->sendLead(['external_id' => 'es12-'.$i])->assertCreated();
        }

        $this->sendLead(['external_id' => 'es12-31'])->assertTooManyRequests();
        // Без токена тоже 429: ограничение стоит раньше проверки токена.
        $this->postJson('/api/leads', $this->payload())->assertTooManyRequests();
        $this->assertSame(30, Deal::query()->count());
    }

    public function test_api_does_not_need_session_or_csrf(): void
    {
        $this->post('/api/leads', $this->payload(), ['Authorization' => 'Bearer '.self::TOKEN, 'Accept' => 'application/json'])
            ->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function sendLead(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/leads', array_merge($this->payload(), $overrides), [
            'Authorization' => 'Bearer '.self::TOKEN,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'external_id' => 'es12-1042',
            'received_at' => '2026-11-17T14:05:00+03:00',
            'name' => 'Иван',
            'phone' => '+79371124897',
            'email' => 'ivan@example.ru',
            'organization' => null,
            'facility' => 'Квартира 60 м²',
            'service' => 'Замена проводки',
            'origin' => 'Яндекс Карты',
            'message' => 'Нужна замена проводки',
            'estimate' => 'от 85 000 ₽',
            'source_url' => 'https://es12.ru/ceny?utm_source=yandex',
        ];
    }
}
