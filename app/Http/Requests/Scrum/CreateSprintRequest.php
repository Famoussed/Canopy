<?php

declare(strict_types=1);

namespace App\Http\Requests\Scrum;

use Illuminate\Foundation\Http\FormRequest;

class CreateSprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [\App\Models\Sprint::class, $this->route('project')]);
    }

    /**
     * BR-07: Sprint tarih kuralları.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->start_date && $this->end_date) {
                $start = \Carbon\Carbon::parse($this->start_date);
                $end = \Carbon\Carbon::parse($this->end_date);
                $days = $start->diffInDays($end);

                if ($days < 1) {
                    $validator->errors()->add('end_date', 'Sprint duration must be at least 1 day.');
                }

                if ($days > 30) {
                    $validator->errors()->add('end_date', 'Sprint duration cannot exceed 30 days.');
                }
            }
        });
    }
}
