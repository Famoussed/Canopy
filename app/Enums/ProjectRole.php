<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';

    /**
     * Hiyerarşik sıra. Yüksek = daha yetkili.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Moderator => 2,
            self::Member => 1,
        };
    }

    /**
     * Bu rol, verilen minimum rolden yüksek veya eşit mi?
     */
    public function isAtLeast(self $minimumRole): bool
    {
        return $this->rank() >= $minimumRole->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proje Sahibi',
            self::Moderator => 'Yardımcı Yönetici',
            self::Member => 'Üye',
        };
    }
}
