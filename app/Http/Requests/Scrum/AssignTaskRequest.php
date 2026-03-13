<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');
        if ($task) {
            $task->loadMissing('userStory.project');
        }

        return $this->user()->can('assign', $task);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'string', 'exists:users,id'],
        ];
    }
}
