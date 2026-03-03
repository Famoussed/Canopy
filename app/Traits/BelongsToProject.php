<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Project;

/**
 * Bir projeye ait olan model'ler için ortak trait.
 * project_id foreign key'i üzerinden Project ilişkisi sağlar.
 */
trait BelongsToProject
{
    /**
     * Bu entity'nin ait olduğu proje.
     */
    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Scope: Belirli bir projeye ait kayıtları filtrele.
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
