<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
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
    use Auditable;
    use BelongsToProject;
    use HasFactory;
    use HasStateMachine;
    use HasUuids;

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

    public function scopeByPriority($query, IssuePriority|string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeBySeverity($query, IssueSeverity|string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByType($query, IssueType|string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, array|string $statuses)
    {
        $statusList = is_array($statuses) ? $statuses : explode(',', $statuses);
        return $query->whereIn('status', $statusList);
    }

    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeFilter($query, array $filters, ?string $userId = null)
    {
        return $query
            ->when(filled($filters['type'] ?? null), fn($q) => $q->byType($filters['type']))
            ->when(filled($filters['priority'] ?? null), fn($q) => $q->byPriority($filters['priority']))
            ->when(filled($filters['severity'] ?? null), fn($q) => $q->bySeverity($filters['severity']))
            ->when(filled($filters['status'] ?? null), fn($q) => $q->byStatus($filters['status']))
            ->when(($filters['assigned_to'] ?? null) === 'me' && $userId, fn($q) => $q->assignedTo($userId));
    }
}
