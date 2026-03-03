<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'settings' => ['sometimes', 'array'],
            'settings.modules' => ['sometimes', 'array'],
            'settings.estimation_roles' => ['sometimes', 'array'],
            'settings.estimation_roles.*' => ['string', 'max:50'],
        ];
    }
}
