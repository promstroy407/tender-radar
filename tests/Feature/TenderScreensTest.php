<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Deal;
use App\Models\Estimate;
use App\Models\EstimateFile;
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
        Offer::factory()->for(Deal::factory()->tender()->state(['contract_id' => $sent->id]))->create(['contract_id' => $sent->id]);
        $dismissed = Contract::factory()->create(['subject' => 'Неинтересный']);
        Deal::factory()->tender()->status(DealStatus::Dismissed)->create(['contract_id' => $dismissed->id]);

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

    public function test_opened_tender_is_marked_viewed_for_this_user_only(): void
    {
        $old = Contract::factory()->create(['subject' => 'Открытый раньше', 'signed_at' => '2026-11-20']);
        Contract::factory()->create(['subject' => 'Ещё не открытый', 'signed_at' => '2026-11-10']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/tenders/'.$old->id)->assertOk();

        // Просмотренный — ниже непросмотренного, хотя он свежее.
        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Ещё не открытый', 'Просмотрено', 'Открытый раньше']);

        $this->actingAs($admin)->get('/?unseen=1')
            ->assertOk()
            ->assertSee('Ещё не открытый')
            ->assertDontSee('Открытый раньше');

        // У другого пользователя свой список.
        $this->actingAs($this->admin())->get('/')->assertOk()->assertDontSee('Просмотрено');
    }

    public function test_filter_with_documents_shows_only_tenders_with_files_in_estimate(): void
    {
        $withDocs = Contract::factory()->create(['subject' => 'Монтаж АПС школы']);
        EstimateFile::factory()->for(Estimate::factory()->state(['contract_id' => $withDocs->id]))->create();
        Contract::factory()->create(['subject' => 'Без документации']);

        $this->actingAs($this->admin())->get('/?docs=1')
            ->assertOk()
            ->assertSee('Монтаж АПС школы')
            ->assertDontSee('Без документации')
            ->assertSee('docs=1', false);

        $this->actingAs($this->admin())->get('/?docs=x')->assertSessionHasErrors('docs');
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

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
