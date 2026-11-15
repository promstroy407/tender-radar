<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Offer;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenderScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_shows_new_contracts_with_counts_and_hides_offered(): void
    {
        $hot = Contract::factory()->hot()->create(['subject' => 'Капремонт школы 7']);
        Contract::factory()->create(['subject' => 'Видеонаблюдение в поликлинике']);
        $sent = Contract::factory()->create(['subject' => 'Уже отправили']);
        Offer::factory()->create(['contract_id' => $sent->id]);
        $dismissed = Contract::factory()->create(['subject' => 'Неинтересный']);
        Offer::factory()->dismissed()->create(['contract_id' => $dismissed->id]);

        $this->actingAs($this->admin())->get('/')
            ->assertOk()
            ->assertSee('Капремонт школы 7')
            ->assertSee('Видеонаблюдение в поликлинике')
            ->assertDontSee('Уже отправили')
            ->assertDontSee('Неинтересный')
            ->assertSee('ГОРЯЧИЙ')
            ->assertSeeInOrder(['Все', '2', 'Горячие', '1']);

        $this->actingAs($this->admin())->get('/?filter=hot')
            ->assertOk()
            ->assertSee($hot->subject)
            ->assertDontSee('Видеонаблюдение в поликлинике');
    }

    public function test_feed_respects_region_threshold(): void
    {
        $region = Region::factory()->create(['min_amount_kopecks' => 500_000_000]);
        Contract::factory()->create(['region_code' => $region->code, 'amount_kopecks' => 400_000_000, 'subject' => 'Дешёвый']);
        Contract::factory()->create(['region_code' => $region->code, 'amount_kopecks' => 600_000_000, 'subject' => 'Дорогой']);

        $this->actingAs($this->admin())->get('/')
            ->assertOk()
            ->assertSee('Дорогой')
            ->assertDontSee('Дешёвый');
    }

    public function test_empty_feed_suggests_demo(): void
    {
        $this->actingAs($this->admin())->get('/')
            ->assertOk()
            ->assertSee('Новых тендеров нет')
            ->assertSee('демо-тендеры');
    }

    public function test_card_shows_contract_winner_and_personal_letter(): void
    {
        $company = Company::factory()->create(['name' => 'ООО «Стройка»', 'email' => 'info@stroyka.test', 'phone' => '+7 900 123-45-67']);
        $contract = Contract::factory()->hot()->create([
            'company_id' => $company->id,
            'subject' => 'Капремонт школы 12',
            'amount_kopecks' => 1_840_000_000,
        ]);

        $this->actingAs($this->admin())->get('/tenders/'.$contract->id)
            ->assertOk()
            ->assertSee('Капремонт школы 12')
            ->assertSee("18\u{00A0}400\u{00A0}000")
            ->assertSee('ООО «Стройка»')
            ->assertSee('info@stroyka.test')
            ->assertSee('ч. 5 ст. 30 44-ФЗ')
            ->assertSee('Поздравляем с победой в закупке «Капремонт школы 12»')
            ->assertSee('ЭОМ, сети связи, пожарная сигнализация')
            ->assertSee('Отправить КП на почту');
    }

    public function test_non_admin_cannot_open_card(): void
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())->get('/tenders/'.$contract->id)->assertForbidden();
    }

    public function test_work_screen_lists_offers_by_status(): void
    {
        $sent = Offer::factory()->create();
        $replied = Offer::factory()->status(OfferStatus::Replied)->create(['note' => 'Созвониться в понедельник']);

        $this->actingAs($this->admin())->get('/work')
            ->assertOk()
            ->assertSee($sent->contract->company->name)
            ->assertDontSee($replied->contract->company->name)
            ->assertSee('Напомнить');

        $this->actingAs($this->admin())->get('/work?status=replied')
            ->assertOk()
            ->assertSee($replied->contract->company->name)
            ->assertSee('Созвониться в понедельник');
    }

    public function test_work_rejects_unknown_status_and_non_admin(): void
    {
        $this->actingAs($this->admin())->get('/work?status=dismissed')->assertSessionHasErrors('status');
        $this->actingAs(User::factory()->create())->get('/work')->assertForbidden();
    }

    public function test_offer_status_can_be_changed(): void
    {
        $offer = Offer::factory()->create();

        $this->actingAs($this->admin())
            ->put('/offers/'.$offer->id, ['status' => 'replied', 'remind_on' => '2026-11-20', 'note' => ' Ждут смету '])
            ->assertRedirect('/work?status=replied');

        $offer->refresh();
        $this->assertSame(OfferStatus::Replied, $offer->status);
        $this->assertSame('2026-11-20', $offer->remind_on?->toDateString());
        $this->assertSame('Ждут смету', $offer->note);
    }

    public function test_offer_update_is_validated_and_protected(): void
    {
        $offer = Offer::factory()->create();

        $this->actingAs($this->admin())
            ->put('/offers/'.$offer->id, ['status' => 'won', 'remind_on' => '20.11.2026', 'note' => str_repeat('я', 2001)])
            ->assertSessionHasErrors(['status', 'remind_on', 'note']);

        $this->actingAs(User::factory()->create())
            ->put('/offers/'.$offer->id, ['status' => 'replied'])
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
