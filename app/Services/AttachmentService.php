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

    public function uploadToTarget(string $type, string $id, UploadedFile $file, User $uploader): Attachment
    {
        $modelMap = [
            'user_story' => \App\Models\UserStory::class,
            'task' => \App\Models\Task::class,
            'issue' => \App\Models\Issue::class,
        ];

        if (! array_key_exists($type, $modelMap)) {
            throw new \InvalidArgumentException("Invalid attachable type: {$type}");
        }

        $model = $modelMap[$type]::findOrFail($id);

        return $this->upload($file, $model, $uploader);
    }

    public function delete(Attachment $attachment): void
    {
        $this->deleteAction->execute($attachment);
    }
}
