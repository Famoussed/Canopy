<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Sprint;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SprintForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|date')]
    public string $startDate = '';

    #[Validate('required|date|after:startDate')]
    public string $endDate = '';

    public function setFromSprint(Sprint $sprint): void
    {
        $this->name = $sprint->name;
        $this->startDate = $sprint->start_date->format('Y-m-d');
        $this->endDate = $sprint->end_date->format('Y-m-d');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
