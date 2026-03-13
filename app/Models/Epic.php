<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoryStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToProject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Epic extends Model
{
    use Auditable;
    use BelongsToProject;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'color',
        'status',
        'completion_percentage',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoryStatus::class,
            'completion_percentage' => 'integer',
            'order' => 'integer',
        ];
    }

    // ─── Relationships ───

    public function userStories(): HasMany
    {
        return $this->hasMany(UserStory::class);
    }

    // ─── Accessors ───

}
