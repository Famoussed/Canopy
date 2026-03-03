<?php

declare(strict_types=1);

namespace App\Events\Scrum;

use App\Models\UserStory;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly UserStory $story,
        public readonly User $creator,
    ) {}
}
