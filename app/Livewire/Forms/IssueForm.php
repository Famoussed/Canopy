<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use App\Models\Issue;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class IssueForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('nullable|string')]
    public string $description = '';

    public string $type = 'bug';

    public string $priority = 'normal';

    public string $severity = 'minor';

    public string $assignedTo = '';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IssueType::class)],
            'priority' => ['required', Rule::enum(IssuePriority::class)],
            'severity' => ['required', Rule::enum(IssueSeverity::class)],
        ];
    }

    public function setFromIssue(Issue $issue): void
    {
        $this->title = $issue->title;
        $this->description = $issue->description ?? '';
        $this->type = $issue->type->value;
        $this->priority = $issue->priority->value;
        $this->severity = $issue->severity->value;
        $this->assignedTo = $issue->assigned_to ?? '';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'severity' => $this->severity,
            'assigned_to' => $this->assignedTo ?: null,
        ];
    }

    public function resetWithDefaults(): void
    {
        $this->reset();
        $this->type = 'bug';
        $this->priority = 'normal';
        $this->severity = 'minor';
    }
}
