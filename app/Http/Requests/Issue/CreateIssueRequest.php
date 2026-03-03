<?php

declare(strict_types=1);

namespace App\Http\Requests\Issue;

use App\Enums\IssuePriority;
use App\Enums\IssueSeverity;
use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [\App\Models\Issue::class, $this->route('project')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', 'string', Rule::in(array_column(IssueType::cases(), 'value'))],
            'priority' => ['nullable', 'string', Rule::in(array_column(IssuePriority::cases(), 'value'))],
            'severity' => ['nullable', 'string', Rule::in(array_column(IssueSeverity::cases(), 'value'))],
            'assigned_to' => ['nullable', 'string', 'exists:users,id'],
        ];
    }
}
