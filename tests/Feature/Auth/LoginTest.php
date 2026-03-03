<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kullanıcı Giriş (Login) Testi
 *
 * Bu test sınıfı, kimlik doğrulama (authentication) sisteminin giriş,
 * kullanıcı bilgisi alma ve çıkış işlemlerinin doğru çalıştığını test eder.
 * Tüm testler /api/auth/* endpoint'lerine JSON istekleri göndererek çalışır.
 *
 * Test Edilen Senaryolar:
 *  - test_user_can_login:
 *    Geçerli e-posta ve şifre ile POST /api/auth/login isteği yapılır.
 *    HTTP 200 dönmesi ve yanıtta id, name, email alanlarının bulunması beklenir.
 *
 *  - test_login_fails_with_invalid_credentials:
 *    Yanlış e-posta veya şifre ile POST /api/auth/login isteği yapılır.
 *    HTTP 401 (Unauthorized) dönmesi beklenir.
 *
 *  - test_user_can_get_own_info:
 *    Giriş yapmış kullanıcı olarak GET /api/auth/me isteği yapılır.
 *    HTTP 200 dönmesi ve yanıttaki id ile email alanlarının kullanıcıya
 *    ait olması beklenir.
 *
 *  - test_user_can_logout:
 *    Giriş yapmış kullanıcı olarak POST /api/auth/logout isteği yapılır.
 *    HTTP 204 (No Content) dönmesi beklenir.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'email']]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_get_own_info(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/auth/logout');

        $response->assertStatus(204);
    }
}
