<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Temel Unit Test Örneği
 *
 * Bu test sınıfı, PHPUnit test altyapısının düzgün çalışıp çalışmadığını
 * doğrulamak için kullanılan basit bir "smoke test" niteliğindedir.
 *
 * Test Edilen Senaryo:
 *  - test_that_true_is_true: assertTrue ile true değerinin gerçekten true
 *    olduğunu kontrol eder. Herhangi bir iş mantığı test etmez; yalnızca
 *    test ortamının sağlıklı şekilde ayağa kalktığını garanti eder.
 */
class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
