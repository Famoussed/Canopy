<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('task'));
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
