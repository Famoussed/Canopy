<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scrum;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\AssignTaskRequest;
use App\Http\Requests\Scrum\ChangeStatusRequest;
use App\Http\Requests\Scrum\CreateTaskRequest;
use App\Http\Requests\Scrum\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function index(UserStory $story): AnonymousResourceCollection
    {
        return TaskResource::collection($this->service->listByStory($story));
    }

    public function store(CreateTaskRequest $request, UserStory $story): JsonResponse
    {
        $task = $this->service->create($request->validated(), $story, $request->user());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task = $this->service->update($task, $request->validated());

        return new TaskResource($task);
    }

    public function changeStatus(ChangeStatusRequest $request, Task $task): TaskResource
    {
        // Service handles loading
        $this->authorize('changeStatus', $task);

        $newStatus = TaskStatus::from($request->validated('status'));

        $task = $this->service->changeStatus($task, $newStatus, $request->user());

        return new TaskResource($task);
    }

    public function assign(AssignTaskRequest $request, Task $task): TaskResource
    {
        $task = $this->service->assignById(
            $task,
            $request->validated('assigned_to'),
            $request->user()
        );

        return new TaskResource($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        // Service or model binding handles logic
        $this->authorize('delete', $task);

        $this->service->delete($task);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
