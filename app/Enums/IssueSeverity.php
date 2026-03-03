<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueSeverity: string
{
    case Wishlist = 'wishlist';
    case Minor = 'minor';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Wishlist => 'İstenen',
            self::Minor => 'Küçük',
            self::Critical => 'Kritik',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Wishlist => 1,
            self::Minor => 2,
            self::Critical => 3,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Wishlist => '#6B7280',
            self::Minor => '#F59E0B',
            self::Critical => '#EF4444',
        };
    }
}
