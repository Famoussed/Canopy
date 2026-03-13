<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class ReorderBacklogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['string', 'exists:user_stories,id'],
        ];
    }
}
