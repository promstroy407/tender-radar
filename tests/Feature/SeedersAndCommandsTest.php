<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersAndCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_seeder_is_idempotent_and_keeps_settings(): void
    {
        $this->seed(RegionSeeder::class);
        Region::query()->whereKey('77')->update(['min_amount_kopecks' => 500_000_000]);
        $this->seed(RegionSeeder::class);

        $this->assertSame(5, Region::query()->count());
        $this->assertSame(500_000_000, Region::query()->findOrFail('77')->min_amount_kopecks);
        $this->assertSame(100_000_000, Region::query()->findOrFail('16')->min_amount_kopecks);
    }

    public function test_demo_seeder_creates_marked_demo_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(5, Contract::query()->count());
        $this->assertSame(2, Contract::query()->hot()->count());
        $this->assertSame(5, Contract::query()->where('subject', 'like', '[Демо]%')->count());
    }

    public function test_create_admin_command(): void
    {
        $this->command('app:create-admin', ['email' => 'owner@example.com', 'name' => 'Владелец'])
            ->expectsQuestion('Пароль (не меньше 12 символов)', 'correct-horse-battery')
            ->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertNotSame('correct-horse-battery', $user->getAuthPassword());
    }

    public function test_create_admin_rejects_short_password(): void
    {
        $this->command('app:create-admin', ['email' => 'owner@example.com', 'name' => 'Владелец'])
            ->expectsQuestion('Пароль (не меньше 12 символов)', 'short')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
