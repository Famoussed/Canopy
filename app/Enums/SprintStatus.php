<?php

declare(strict_types=1);

namespace App\Enums;

enum SprintStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Closed = 'closed';

    /**
     * @return array<string, string[]>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::Planning->value => [self::Active->value],
            self::Active->value => [self::Closed->value],
            // Closed sprint yeniden açılamaz
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        $allowed = self::allowedTransitions()[$this->value] ?? [];

        return in_array($target->value, $allowed, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planlama',
            self::Active => 'Aktif',
            self::Closed => 'Kapatıldı',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planning => '#F59E0B',
            self::Active => '#3B82F6',
            self::Closed => '#6B7280',
        };
    }
}
