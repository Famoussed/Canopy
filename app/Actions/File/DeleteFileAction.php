<?php

declare(strict_types=1);

namespace App\Actions\File;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class DeleteFileAction
{
    public function execute(Attachment $attachment): void
    {
        // S3'ten dosyayı sil
        Storage::disk('s3')->delete($attachment->path);

        // Veritabanı kaydını sil
        $attachment->delete();
    }
}
