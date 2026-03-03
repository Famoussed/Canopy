<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SprintScopeChange extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sprint_id',
        'user_story_id',
        'change_type',
        'changed_at',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function userStory(): BelongsTo
    {
        return $this->belongsTo(UserStory::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
