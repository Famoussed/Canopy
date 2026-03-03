<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class EstimateStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estimate', $this->route('story'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'array', 'min:1'],
            'points.*.role_name' => ['required', 'string', 'max:50'],
            'points.*.points' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
