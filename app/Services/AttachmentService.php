<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\File\DeleteFileAction;
use App\Actions\File\UploadFileAction;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class AttachmentService
{
    public function __construct(
        private readonly UploadFileAction $uploadAction,
        private readonly DeleteFileAction $deleteAction,
    ) {}

    public function upload(UploadedFile $file, Model $attachable, User $uploader): Attachment
    {
        return $this->uploadAction->execute($file, $attachable, $uploader);
    }

    public function delete(Attachment $attachment): void
    {
        $this->deleteAction->execute($attachment);
    }
}
