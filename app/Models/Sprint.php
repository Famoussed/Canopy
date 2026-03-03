<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SprintStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToProject;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use Auditable, BelongsToProject, HasFactory, HasStateMachine, HasUuids;

    protected $fillable = [
        'project_id',
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => SprintStatus::class,
        ];
    }

    // ─── Relationships ───

    public function userStories(): HasMany
    {
        return $this->hasMany(UserStory::class);
    }

    public function scopeChanges(): HasMany
    {
        return $this->hasMany(SprintScopeChange::class);
    }

    // ─── Scopes ───

    public function scopeActive($query)
    {
        return $query->where('status', SprintStatus::Active);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', SprintStatus::Closed);
    }

    public function scopePlanning($query)
    {
        return $query->where('status', SprintStatus::Planning);
    }
}
