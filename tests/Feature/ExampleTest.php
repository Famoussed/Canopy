<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temel Feature Test Örneği
 *
 * Bu test sınıfı, uygulamanın ana sayfasının (/) HTTP üzerinden erişilebilir
 * olup olmadığını doğrulayan basit bir "smoke test" niteliğindedir.
 *
 * Test Edilen Senaryo:
 *  - test_the_application_returns_a_successful_response:
 *    GET / isteği yapılır ve HTTP 200 (OK) yanıt kodu dönmesi beklenir.
 *    Uygulamanın temel seviyede ayakta olduğunu doğrular.
 */
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
