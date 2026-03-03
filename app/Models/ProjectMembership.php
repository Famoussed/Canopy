<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMembership extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }

    // ─── Relationships ───

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ───

    public function isOwner(): bool
    {
        return $this->role === ProjectRole::Owner;
    }

    public function isAtLeast(ProjectRole $minimumRole): bool
    {
        return $this->role->isAtLeast($minimumRole);
    }
}
