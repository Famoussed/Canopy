<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskStatus;
use App\Traits\Auditable;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use Auditable, HasFactory, HasStateMachine, HasUuids;

    protected $fillable = [
        'user_story_id',
        'title',
        'description',
        'status',
        'assigned_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
        ];
    }

    // ─── Relationships ───

    public function userStory(): BelongsTo
    {
        return $this->belongsTo(UserStory::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    // ─── Scopes ───

    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeByStatus($query, TaskStatus $status)
    {
        return $query->where('status', $status);
    }

    // ─── Helpers ───

    /**
     * Task'ın ait olduğu projeyi UserStory üzerinden al.
     */
    public function getProjectAttribute(): ?Project
    {
        return $this->userStory?->project;
    }
}
