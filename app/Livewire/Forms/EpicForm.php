<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Epic;
use Livewire\Attributes\Validate;
use Livewire\Form;

class EpicForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|string|max:7')]
    public string $color = '#6366F1';

    public function setFromEpic(Epic $epic): void
    {
        $this->title = $epic->title;
        $this->description = $epic->description ?? '';
        $this->color = $epic->color ?? '#6366F1';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'color' => $this->color,
        ];
    }

    public function resetWithDefaults(): void
    {
        $this->reset();
        $this->color = '#6366F1';
    }
}
