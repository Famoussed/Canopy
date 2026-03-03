<?php

declare(strict_types=1);

namespace App\Actions\File;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadFileAction
{
    public function execute(UploadedFile $file, Model $attachable, User $uploader): Attachment
    {
        $path = $file->store(
            "attachments/{$attachable->getMorphClass()}/{$attachable->id}",
            's3'
        );

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);
    }
}
