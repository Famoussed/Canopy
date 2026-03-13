<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('sprint'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
        ];
    }
}
