<?php

declare(strict_types=1);

namespace App\Listeners;

class LogActivity
{
    /**
     * Tüm domain event'lerini dinleyerek activity_logs tablosuna kayıt oluşturur.
     */
    public function handle(object $event): void
    {
        // Event tipine göre action ve subject belirlenir.
        // Her event'in ortak alanları: changedBy/creator, entity
        // Bu listener implement edildiğinde event inspection yapılacak.
    }
}
