<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_shown(): void
    {
        $this->get('/login')->assertOk()->assertSee('Вход');
    }

    public function test_user_logs_in_with_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-battery']);

        $this->post('/login', ['email' => 'Owner@Example.com ', 'password' => 'correct-horse-battery'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-battery']);

        $this->from('/login')
            ->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Неверная почта или пароль.']);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-battery']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_validation_of_fields(): void
    {
        $this->post('/login', ['email' => 'not-an-email', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_logged_in_user_sees_home_and_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertSee($user->name);
        $this->actingAs($user)->get('/login')->assertRedirect('/');

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
