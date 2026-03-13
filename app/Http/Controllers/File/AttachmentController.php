<?php

declare(strict_types=1);

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Http\Requests\File\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $service,
    ) {}

    public function store(UploadAttachmentRequest $request): AttachmentResource
    {
        $modelMap = [
            'user_story' => \App\Models\UserStory::class,
            'task' => \App\Models\Task::class,
            'issue' => \App\Models\Issue::class,
        ];

        $model = $modelMap[$request->validated('attachable_type')]::findOrFail(
            $request->validated('attachable_id')
        );

        $attachment = $this->service->upload(
            $request->file('file'),
            $model,
            $request->user()
        );

        return new AttachmentResource($attachment);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->service->delete($attachment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
