<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_its_icons_exist(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($manifest);
        $this->assertSame('Тендер-Радар', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            $sizes[] = $icon['sizes'];
        }
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    public function test_pages_link_manifest_and_register_service_worker(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<link rel="manifest" href="/manifest.json">', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js')", false);
    }

    public function test_home_page_has_no_install_button(): void
    {
        // Ставится значком браузера в адресной строке, своей кнопки в приложении нет.
        $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertOk()
            ->assertDontSee('Установить приложение')
            ->assertDontSee('beforeinstallprompt', false);
    }

    public function test_service_worker_does_not_cache_app_data(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("event.request.mode !== 'navigate'", $sw);
        $this->assertStringNotContainsString('/api/', $sw);
    }
}
