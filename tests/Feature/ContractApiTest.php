<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\WorkSection;
use App\Models\Contract;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/contracts')->assertUnauthorized();
    }

    public function test_non_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/contracts')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_open_contract(): void
    {
        $contract = Contract::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/contracts/'.$contract->id)
            ->assertForbidden();
    }

    public function test_admin_sees_newest_first_with_company(): void
    {
        $older = Contract::factory()->create(['signed_at' => '2026-11-01']);
        $newer = Contract::factory()->create(['signed_at' => '2026-11-10']);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.0.company.inn', $newer->company->inn)
            ->assertJsonPath('data.0.amount_kopecks', $newer->amount_kopecks);
    }

    public function test_hot_filter_returns_only_hot_contracts(): void
    {
        $hot = Contract::factory()->hot()->create();
        Contract::factory()->create();
        Contract::factory()->create(['requires_smp_subcontract' => true, 'sections' => []]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts?filter=hot')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hot->id)
            ->assertJsonPath('data.0.is_hot', true);
    }

    public function test_kind_filter(): void
    {
        $general = Contract::factory()->general()->create();
        Contract::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts?filter=general')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $general->id)
            ->assertJsonPath('data.0.kind.label', 'Генподряд');
    }

    public function test_region_and_amount_filters(): void
    {
        $region = Region::factory()->create();
        $match = Contract::factory()->create(['region_code' => $region->code, 'amount_kopecks' => 500_000_000]);
        Contract::factory()->create(['region_code' => $region->code, 'amount_kopecks' => 50_000_000]);
        Contract::factory()->create(['amount_kopecks' => 500_000_000]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts?'.http_build_query([
                'regions' => [$region->code],
                'min_amount_kopecks' => 100_000_000,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_disabled_regions_are_hidden(): void
    {
        $disabled = Region::factory()->create(['is_enabled' => false]);
        Contract::factory()->create(['region_code' => $disabled->code]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts?'.http_build_query([
                'filter' => 'secret',
                'regions' => ['xx'],
                'min_amount_kopecks' => -1,
                'per_page' => 1000,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter', 'regions.0', 'min_amount_kopecks', 'per_page']);
    }

    public function test_admin_opens_contract_card(): void
    {
        $contract = Contract::factory()->hot()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/contracts/'.$contract->id)
            ->assertOk()
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.is_hot', true)
            ->assertJsonPath('data.sections.0.value', WorkSection::Eom->value)
            ->assertJsonPath('data.company.name', $contract->company->name)
            ->assertJsonPath('data.law.label', '44-ФЗ');
    }
}
