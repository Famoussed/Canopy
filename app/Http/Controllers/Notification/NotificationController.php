<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\MarkNotificationReadRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->unread()
            ->latest()
            ->paginate(20);

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $request->user()->notifications()->unread()->count()]]);
    }

    public function markRead(MarkNotificationReadRequest $request): JsonResponse
    {
        $this->service->markAsRead($request->validated('id'), $request->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->service->markAllAsRead($request->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
