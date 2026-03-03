<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastProjectUpdate
{
    /**
     * Sprint board ve backlog'daki değişiklikleri real-time olarak
     * tüm proje üyelerine broadcast eder.
     */
    public function handle(object $event): void
    {
        // Broadcasting implementasyonu
        // Laravel Echo + Reverb ile proje kanalına broadcast edilecek.
    }
}
