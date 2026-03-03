<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ActivityLog;

/**
 * Audit logging desteği sağlayan trait.
 * Polymorphic ilişki ile activity_logs tablosuna bağlanır.
 */
trait Auditable
{
    /**
     * Bu entity ile ilişkili aktivite logları.
     */
    public function activityLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
