<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Traits\Auditable;
use App\Traits\BelongsToProject;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Issue extends Model
{
    use Auditable, BelongsToProject, HasFactory, HasStateMachine, HasUuids;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'type',
        'priority',
        'severity',
        'status',
        'assigned_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'priority' => IssuePriority::class,
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
        ];
    }

    // ─── Relationships ───

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

    public function scopeOpen($query)
    {
        return $query->where('status', '!=', IssueStatus::Done);
    }

    public function scopeByPriority($query, IssuePriority $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeBySeverity($query, IssueSeverity $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByType($query, IssueType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }
}
