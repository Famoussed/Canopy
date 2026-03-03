<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kullanıcı Kayıt (Register) Testi
 *
 * Bu test sınıfı, yeni kullanıcı kayıt işleminin doğru çalıştığını,
 * validasyon kurallarının uygulandığını ve benzersiz e-posta kontrolünün
 * yapıldığını test eder. Tüm istekler POST /api/auth/register endpoint'ine yapılır.
 *
 * Test Edilen Senaryolar:
 *  - test_user_can_register:
 *    Geçerli name, email, password ve password_confirmation verileriyle
 *    kayıt isteği yapılır. HTTP 201 (Created) dönmesi, yanıtta id, name,
 *    email, created_at alanlarının bulunması ve veritabanında ilgili
 *    e-posta adresinin mevcut olması beklenir.
 *
 *  - test_registration_requires_valid_data:
 *    Boş bir istek gönderilir. HTTP 422 (Unprocessable Entity) dönmesi
 *    ve name, email, password alanlarında validasyon hataları olması beklenir.
 *
 *  - test_registration_requires_unique_email:
 *    Zaten kayıtlı olan bir e-posta adresiyle tekrar kayıt yapılmaya
 *    çalışılır. HTTP 422 dönmesi ve email alanında validasyon hatası
 *    olması beklenir. (Benzersiz e-posta kuralı.)
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at']]);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
