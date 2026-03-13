<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoryStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToProject;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class UserStory extends Model
{
    use Auditable;
    use BelongsToProject;
    use HasFactory;
    use HasStateMachine;
    use HasUuids;

    protected $fillable = [
        'project_id',
        'epic_id',
        'sprint_id',
        'title',
        'description',
        'status',
        'total_points',
        'custom_fields',
        'order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoryStatus::class,
            'total_points' => 'decimal:2',
            'custom_fields' => 'json',
            'order' => 'integer',
        ];
    }

    // ─── Relationships ───

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function storyPoints(): HasMany
    {
        return $this->hasMany(StoryPoint::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ─── Scopes ───

    public function scopeBacklog($query)
    {
        return $query->whereNull('sprint_id');
    }

    public function scopeInSprint($query, string $sprintId)
    {
        return $query->where('sprint_id', $sprintId);
    }

    public function scopeByStatus($query, StoryStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
