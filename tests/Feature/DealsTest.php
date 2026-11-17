<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DealSource;
use App\Enums\DealStatus;
use App\Enums\OfferChannel;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DealsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deals_screen_lists_by_status_and_source(): void
    {
        Deal::factory()->create(['organization' => 'ООО «Квартал»']);
        $tender = Deal::factory()->tender()->status(DealStatus::New)->create();
        Deal::factory()->status(DealStatus::Negotiation)->create(['organization' => 'ИП Петров']);

        $this->actingAs($this->admin())->get('/deals')
            ->assertOk()
            ->assertSee('ООО «Квартал»')
            ->assertSee($tender->company->name ?? '')
            ->assertDontSee('ИП Петров');

        $this->actingAs($this->admin())->get('/deals?status=new&source=site')
            ->assertOk()
            ->assertSee('ООО «Квартал»')
            ->assertDontSee($tender->company->name ?? '');

        $this->actingAs($this->admin())->get('/deals?status=negotiation')
            ->assertOk()
            ->assertSee('ИП Петров');
    }

    public function test_deals_screen_validates_filters_and_access(): void
    {
        $this->actingAs($this->admin())->get('/deals?status=won')->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->get('/deals?source=fax')->assertSessionHasErrors('source');
        $this->actingAs(User::factory()->create())->get('/deals')->assertForbidden();
        $this->actingAs($this->admin())->get('/work')->assertRedirect('/deals');
    }

    public function test_card_shows_lead_fields(): void
    {
        $deal = Deal::factory()->create([
            'name' => 'Иван Сидоров',
            'phone' => '+79371112233',
            'message' => 'Нужна замена проводки',
            'estimate' => 'от 85 000 ₽',
            'external_id' => 'es12-501',
        ]);

        $this->actingAs($this->admin())->get('/deals/'.$deal->id)
            ->assertOk()
            ->assertSee('Иван Сидоров')
            ->assertSee('+79371112233')
            ->assertSee('Нужна замена проводки')
            ->assertSee('от 85 000 ₽')
            ->assertSee('es12-501');

        $this->actingAs(User::factory()->create())->get('/deals/'.$deal->id)->assertForbidden();
    }

    public function test_tender_card_links_to_contract(): void
    {
        $deal = Deal::factory()->tender()->create();
        Offer::factory()->for($deal)->create(['contract_id' => $deal->contract_id, 'channel' => OfferChannel::WhatsApp]);

        $this->actingAs($this->admin())->get('/deals/'.$deal->id)
            ->assertOk()
            ->assertSee('/tenders/'.$deal->contract_id, false)
            ->assertSee('WhatsApp');
    }

    public function test_status_buttons_change_status_and_keep_reached_stage(): void
    {
        $deal = Deal::factory()->create();

        $this->actingAs($this->admin())->put('/deals/'.$deal->id.'/status', ['status' => 'negotiation'])
            ->assertRedirect('/deals/'.$deal->id);
        $deal->refresh();
        $this->assertSame(DealStatus::Negotiation, $deal->status);
        $this->assertSame(DealStatus::Negotiation, $deal->reached_status);

        // Отказ после переговоров: в воронке сделка остаётся дошедшей до переговоров.
        $this->actingAs($this->admin())->put('/deals/'.$deal->id.'/status', ['status' => 'refused']);
        $deal->refresh();
        $this->assertSame(DealStatus::Refused, $deal->status);
        $this->assertSame(DealStatus::Negotiation, $deal->reached_status);
        $this->assertNull($deal->remind_on);

        // Возврат на более ранний этап не уменьшает достигнутый.
        $this->actingAs($this->admin())->put('/deals/'.$deal->id.'/status', ['status' => 'brief_received']);
        $this->assertSame(DealStatus::Negotiation, $deal->refresh()->reached_status);
    }

    public function test_status_change_is_validated_and_protected(): void
    {
        $deal = Deal::factory()->create();

        $this->actingAs($this->admin())->put('/deals/'.$deal->id.'/status', ['status' => 'won'])
            ->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->put('/deals/'.$deal->id.'/status', [])
            ->assertSessionHasErrors('status');
        $this->actingAs(User::factory()->create())->put('/deals/'.$deal->id.'/status', ['status' => 'contract'])
            ->assertForbidden();

        $this->assertSame(DealStatus::New, $deal->refresh()->status);
    }

    public function test_amounts_are_saved_in_kopecks(): void
    {
        $deal = Deal::factory()->create();

        $this->actingAs($this->admin())->put('/deals/'.$deal->id, [
            'offer_amount' => '1 250 000,5',
            'contract_amount' => '1200000',
            'paid' => '300000,05',
            'remind_on' => '2026-11-25',
            'note' => '  Аванс 25 %  ',
        ])->assertRedirect('/deals/'.$deal->id)->assertSessionHasNoErrors();

        $deal->refresh();
        $this->assertSame(125_000_050, $deal->offer_amount_kopecks);
        $this->assertSame(120_000_000, $deal->contract_amount_kopecks);
        $this->assertSame(30_000_005, $deal->paid_kopecks);
        $this->assertSame(89_999_995, $deal->remainderKopecks());
        $this->assertSame('2026-11-25', $deal->remind_on?->toDateString());
        $this->assertSame('Аванс 25 %', $deal->note);

        // Пустые поля стирают суммы.
        $this->actingAs($this->admin())->put('/deals/'.$deal->id, ['offer_amount' => '', 'contract_amount' => '', 'paid' => '', 'note' => ' ']);
        $deal->refresh();
        $this->assertNull($deal->offer_amount_kopecks);
        $this->assertNull($deal->contract_amount_kopecks);
        $this->assertNull($deal->paid_kopecks);
        $this->assertNull($deal->note);
    }

    public function test_amounts_are_validated(): void
    {
        $deal = Deal::factory()->amounts(100)->create();

        $this->actingAs($this->admin())->put('/deals/'.$deal->id, [
            'offer_amount' => '12,345',
            'contract_amount' => '-5',
            'paid' => 'сто',
            'remind_on' => '25.11.2026',
            'note' => str_repeat('я', 2001),
        ])->assertSessionHasErrors(['offer_amount', 'contract_amount', 'paid', 'remind_on', 'note']);

        $this->actingAs($this->admin())->put('/deals/'.$deal->id, ['contract_amount' => '1000', 'paid' => '1000,01'])
            ->assertSessionHasErrors('paid');

        $this->actingAs(User::factory()->create())->put('/deals/'.$deal->id, ['offer_amount' => '1'])->assertForbidden();

        $this->assertSame(100, $deal->refresh()->offer_amount_kopecks);
    }

    public function test_tender_page_links_to_its_deal(): void
    {
        $deal = Deal::factory()->tender()->status(DealStatus::Negotiation)->create();
        Offer::factory()->for($deal)->create(['contract_id' => $deal->contract_id]);

        $this->actingAs($this->admin())->get('/tenders/'.$deal->contract_id)
            ->assertOk()
            ->assertSee('/deals/'.$deal->id, false)
            ->assertSee('Переговоры');
    }

    public function test_migration_moves_offer_statuses_into_deals_and_back(): void
    {
        $this->runDealsMigration('down');

        $rows = [
            'sent' => Contract::factory()->create(),
            'replied' => Contract::factory()->create(),
            'deal' => Contract::factory()->create(),
            'refused' => Contract::factory()->create(),
            'dismissed' => Contract::factory()->create(),
        ];
        foreach ($rows as $status => $contract) {
            DB::table('offers')->insert([
                'contract_id' => $contract->id,
                'status' => $status,
                'channel' => $status === 'dismissed' ? null : 'email',
                'sent_at' => $status === 'dismissed' ? null : now(),
                'remind_on' => $status === 'sent' ? '2026-11-20' : null,
                'note' => $status === 'replied' ? 'Ждут смету' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->runDealsMigration('up');

        $expected = [
            'sent' => ['offer_sent', 'offer_sent'],
            'replied' => ['negotiation', 'negotiation'],
            'deal' => ['contract', 'contract'],
            'refused' => ['refused', 'offer_sent'],
            'dismissed' => ['dismissed', 'new'],
        ];
        foreach ($expected as $old => [$status, $reached]) {
            $deal = Deal::query()->where('contract_id', $rows[$old]->id)->sole();
            $this->assertSame(DealSource::Tender, $deal->source);
            $this->assertSame($status, $deal->status->value, $old);
            $this->assertSame($reached, $deal->reached_status->value, $old);
            $this->assertSame($rows[$old]->company_id, $deal->company_id);
        }
        $this->assertSame('Ждут смету', Deal::query()->where('contract_id', $rows['replied']->id)->value('note'));
        $this->assertSame('2026-11-20', Deal::query()->where('contract_id', $rows['sent']->id)->sole()->remind_on?->toDateString());
        // «Не интересно» без отправки — только сделка, отправки нет.
        $this->assertSame(4, Offer::query()->count());
        $this->assertFalse(Schema::hasColumn('offers', 'status'));

        // Откат возвращает статусы КП.
        $this->runDealsMigration('down');
        $this->assertSame(
            ['deal', 'dismissed', 'refused', 'replied', 'sent'],
            DB::table('offers')->orderBy('status')->pluck('status')->all(),
        );
        $this->assertSame('Ждут смету', DB::table('offers')->where('status', 'replied')->value('note'));
        $this->runDealsMigration('up');
    }

    public function test_migration_rollback_refuses_to_drop_site_leads(): void
    {
        Deal::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->runDealsMigration('down');
    }

    /** Прогоняет миграцию сделок вручную: up или down. */
    private function runDealsMigration(string $direction): void
    {
        $migration = require database_path('migrations/2026_11_17_000001_create_deals_table.php');
        $this->assertInstanceOf(Migration::class, $migration);

        if ($direction === 'up' && method_exists($migration, 'up')) {
            $migration->up();
        } elseif ($direction === 'down' && method_exists($migration, 'down')) {
            $migration->down();
        }
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
