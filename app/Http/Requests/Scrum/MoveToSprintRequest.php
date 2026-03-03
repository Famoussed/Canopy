<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class MoveToSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moveToSprint', $this->route('story'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sprint_id' => ['required', 'string', 'exists:sprints,id'],
        ];
    }
}
