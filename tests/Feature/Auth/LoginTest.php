<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kullanıcı Giriş (Login) Testi
 *
 * Livewire auth.login bileşeninin giriş işlemini test eder.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['password' => bcrypt('password123')]);

        Livewire::test('auth.login')
            ->set('email', 'wrong@example.com')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_requires_email(): void
    {
        Livewire::test('auth.login')
            ->set('email', '')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $user = User::factory()->create();

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['password']);
    }
}
