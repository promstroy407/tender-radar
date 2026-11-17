<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LeadsTokenCommandTest extends TestCase
{
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
        $this->artisan('leads:token')
            ->expectsOutputToContain('показывается один раз')
            ->assertSuccessful();

        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^LEADS_API_TOKEN=[0-9a-f]{64}$/m', $env);
        $this->assertSame(1, substr_count($env, 'LEADS_API_TOKEN='));
        $this->assertStringContainsString('APP_NAME=Test', $env);

        // Повторный запуск не меняет токен и не показывает его.
        $this->artisan('leads:token')->expectsOutputToContain('Токен уже задан')->assertSuccessful();
        $this->assertSame($env, file_get_contents($this->dir.'/.env'));
    }

    public function test_force_replaces_token(): void
    {
        $this->artisan('leads:token')->assertSuccessful();
        $before = (string) file_get_contents($this->dir.'/.env');

        $this->artisan('leads:token', ['--force' => true])->assertSuccessful();
        $after = (string) file_get_contents($this->dir.'/.env');

        $this->assertNotSame($before, $after);
        $this->assertSame(1, substr_count($after, 'LEADS_API_TOKEN='));
    }

    public function test_appends_line_when_missing(): void
    {
        file_put_contents($this->dir.'/.env', 'APP_NAME=Test');

        $this->artisan('leads:token')->assertSuccessful();

        $this->assertMatchesRegularExpression('/^APP_NAME=Test\nLEADS_API_TOKEN=[0-9a-f]{64}\n$/', (string) file_get_contents($this->dir.'/.env'));
    }

    public function test_missing_env_file_fails(): void
    {
        unlink($this->dir.'/.env');

        $this->artisan('leads:token')->expectsOutputToContain('.env не найден')->assertFailed();
    }
}
