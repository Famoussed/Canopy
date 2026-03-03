<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    /**
     * @return array<string, string[]>
     */
    public static function allowedTransitions(): array
    {
        return [
            self::New->value => [self::InProgress->value],
            self::InProgress->value => [self::New->value, self::Done->value],
            self::Done->value => [self::InProgress->value],
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
            self::New => 'Yeni',
            self::InProgress => 'Devam Ediyor',
            self::Done => 'Tamamlandı',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => '#6B7280',
            self::InProgress => '#3B82F6',
            self::Done => '#10B981',
        };
    }
}
