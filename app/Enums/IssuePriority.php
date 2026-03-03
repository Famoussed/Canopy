<?php

declare(strict_types=1);

namespace App\Enums;

enum IssuePriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Düşük',
            self::Normal => 'Normal',
            self::High => 'Yüksek',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => '#6B7280',
            self::Normal => '#F59E0B',
            self::High => '#EF4444',
        };
    }
}
