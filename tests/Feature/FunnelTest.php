<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\User;
use App\Services\FunnelService;
use App\Services\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_stages_conversion_money_and_sources(): void
    {
        // Сайт: 2 новые, 1 в переговорах, 1 отказ после переговоров, 1 сдан и оплачен.
        Deal::factory()->count(2)->create();
        Deal::factory()->status(DealStatus::Negotiation)->amounts(50_000_000)->create();
        Deal::factory()->status(DealStatus::Refused, DealStatus::Negotiation)->amounts(30_000_000)->create();
        Deal::factory()->status(DealStatus::DonePaid)->amounts(120_000_050, 100_000_000, 100_000_000)->create();
        // Тендеры: 1 отправлено КП, 1 договор с авансом, 1 «Не интересно».
        Deal::factory()->tender()->create();
        Deal::factory()->tender()->status(DealStatus::Contract)->amounts(null, 250_000_033, 75_000_000)->create();
        Deal::factory()->tender()->status(DealStatus::Dismissed)->create();

        $report = $this->app->make(FunnelService::class)->report(null);

        $this->assertSame(8, $report->total);
        $reached = array_map(fn (array $row): int => $row['reached'], $report->stages);
        // new, brief, offer, negotiation, contract, in_progress, done_paid
        $this->assertSame([8, 5, 5, 4, 2, 1, 1], $reached);

        $offerSent = $report->stages[2];
        $this->assertSame(DealStatus::OfferSent, $offerSent['status']);
        $this->assertSame(1, $offerSent['now']);
        $this->assertSame(1000, $offerSent['fromPrevious']);
        $this->assertSame(625, $offerSent['fromAll']);
        // 4 из 5 = 80 %; 2 из 4 = 50 %; от всех 2 из 8 = 25 %.
        $this->assertSame(800, $report->stages[3]['fromPrevious']);
        $this->assertSame(500, $report->stages[4]['fromPrevious']);
        $this->assertSame(250, $report->stages[4]['fromAll']);
        $this->assertNull($report->stages[0]['fromPrevious']);

        $this->assertSame([1, 1], array_map(fn (array $row): int => $row['count'], $report->exits));

        $this->assertSame(200_000_050, $report->offerKopecks);
        $this->assertSame(350_000_033, $report->contractKopecks);
        $this->assertSame(175_000_000, $report->paidKopecks);
        $this->assertSame(175_000_033, $report->remainderKopecks);

        $bySource = [];
        foreach ($report->sources as $row) {
            $bySource[$row['source']->value] = [$row['deals'], $row['contracts'], $row['contractKopecks']];
        }
        $this->assertSame([5, 1, 100_000_000], $bySource['site']);
        $this->assertSame([3, 1, 250_000_033], $bySource['tender']);
        $this->assertSame([0, 0, 0], $bySource['call']);
    }

    public function test_report_filters_by_source_and_handles_empty(): void
    {
        $empty = $this->app->make(FunnelService::class)->report(null);
        $this->assertSame(0, $empty->total);
        $this->assertNull($empty->stages[0]['fromAll']);

        Deal::factory()->create();
        Deal::factory()->count(2)->tender()->create();

        $report = $this->app->make(FunnelService::class)->report(DealSource::Tender);
        $this->assertSame(2, $report->total);
        $this->assertCount(1, $report->sources);
        $this->assertSame(DealSource::Tender, $report->sources[0]['source']);
    }

    public function test_percent_rounds_half_up_without_float(): void
    {
        Deal::factory()->count(2)->create();
        Deal::factory()->status(DealStatus::BriefReceived)->create();

        $report = $this->app->make(FunnelService::class)->report(null);
        $money = $this->app->make(MoneyFormatter::class);

        // 1 из 3 = 33,33… → 33,3 %; 2 из 3 = 66,66… → 66,7 %.
        $this->assertSame("33,3\u{00A0}%", $money->percent($report->stages[1]['fromAll']));
        $this->assertSame("100\u{00A0}%", $money->percent($report->stages[0]['fromAll']));
        $this->assertSame('—', $money->percent(null));
    }

    public function test_funnel_screen_shows_report_and_is_protected(): void
    {
        Deal::factory()->status(DealStatus::Contract)->amounts(null, 150_000_000)->create();

        $this->actingAs(User::factory()->admin()->create())->get('/funnel')
            ->assertOk()
            ->assertSee('Воронка')
            ->assertSee('Переговоры')
            ->assertSee("1\u{00A0}500\u{00A0}000\u{00A0}₽")
            ->assertSee('Источники');

        $this->actingAs(User::factory()->admin()->create())->get('/funnel?source=site')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/funnel?source=fax')->assertSessionHasErrors('source');
        $this->actingAs(User::factory()->create())->get('/funnel')->assertForbidden();
    }
}
