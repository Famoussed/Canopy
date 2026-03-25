<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\UserStory;
use Livewire\Attributes\Validate;
use Livewire\Form;

class StoryForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string|max:10000')]
    public string $description = '';

    #[Validate('nullable|string')]
    public ?string $epicId = null;

    public function setFromStory(UserStory $story): void
    {
        $this->title = $story->title;
        $this->description = $story->description ?? '';
        $this->epicId = $story->epic_id;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'epic_id' => $this->epicId,
        ];
    }
}
