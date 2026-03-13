<?php

declare(strict_types=1);

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
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
            'attachable_type' => ['required', 'string', 'in:user_story,task,issue'],
            'attachable_id' => ['required', 'string'],
            'file' => ['required', 'file', 'max:10240'], // 10MB
        ];
    }
}
