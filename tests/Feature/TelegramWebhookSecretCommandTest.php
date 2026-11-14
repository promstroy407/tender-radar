<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class TelegramWebhookSecretCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/tr-env-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/.env');
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_fills_empty_secret_without_printing_it(): void
    {
        file_put_contents($this->dir.'/.env', "APP_NAME=x\nTELEGRAM_WEBHOOK_SECRET=\nOTHER=1\n");

        $this->command('telegram:webhook-secret')->assertSuccessful();

        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^TELEGRAM_WEBHOOK_SECRET=[0-9a-f]{64}$/m', $env);
        $this->assertStringContainsString("APP_NAME=x\n", $env);
        $this->assertStringContainsString("OTHER=1\n", $env);
    }

    public function test_appends_secret_when_line_is_missing(): void
    {
        file_put_contents($this->dir.'/.env', "APP_NAME=x\n");

        $this->command('telegram:webhook-secret')->assertSuccessful();

        $this->assertMatchesRegularExpression('/^TELEGRAM_WEBHOOK_SECRET=[0-9a-f]{64}$/m', (string) file_get_contents($this->dir.'/.env'));
    }

    public function test_keeps_existing_secret_without_force(): void
    {
        file_put_contents($this->dir.'/.env', "TELEGRAM_WEBHOOK_SECRET=keep-me\n");

        $this->command('telegram:webhook-secret')->assertSuccessful();

        $this->assertSame("TELEGRAM_WEBHOOK_SECRET=keep-me\n", file_get_contents($this->dir.'/.env'));
    }

    public function test_force_replaces_secret(): void
    {
        file_put_contents($this->dir.'/.env', "TELEGRAM_WEBHOOK_SECRET=old\n");

        $this->command('telegram:webhook-secret', ['--force' => true])->assertSuccessful();

        $env = (string) file_get_contents($this->dir.'/.env');
        $this->assertStringNotContainsString('=old', $env);
        $this->assertMatchesRegularExpression('/^TELEGRAM_WEBHOOK_SECRET=[0-9a-f]{64}$/m', $env);
    }
}
