<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueType: string
{
    case Bug = 'bug';
    case Question = 'question';
    case Enhancement = 'enhancement';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Hata',
            self::Question => 'Soru',
            self::Enhancement => 'İyileştirme',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bug => 'bug-ant',
            self::Question => 'question-mark-circle',
            self::Enhancement => 'light-bulb',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bug => '#EF4444',
            self::Question => '#8B5CF6',
            self::Enhancement => '#10B981',
        };
    }
}
