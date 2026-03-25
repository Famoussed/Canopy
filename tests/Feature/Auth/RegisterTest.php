<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kullanıcı Kayıt (Register) Testi
 *
 * Livewire auth.register bileşeninin kayıt işlemini test eder.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_renders(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_user_can_register(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
        $this->assertAuthenticated();
    }

    public function test_registration_requires_valid_data(): void
    {
        Livewire::test('auth.register')
            ->set('name', '')
            ->set('email', '')
            ->set('password', '')
            ->call('register')
            ->assertHasErrors(['name', 'email', 'password']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        Livewire::test('auth.register')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'different')
            ->call('register')
            ->assertHasErrors(['password']);
    }
}
