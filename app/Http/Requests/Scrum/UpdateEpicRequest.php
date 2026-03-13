<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEpicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('epic'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', 'string', 'max:7'],
        ];
    }
}
