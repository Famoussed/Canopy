<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scrum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\ChangeStatusRequest;
use App\Http\Requests\Scrum\CreateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    public function __construct(private TaskService $service) {}

    public function index(UserStory $story): AnonymousResourceCollection
    {
        return TaskResource::collection($story->tasks()->with('assignee')->get());
    }

    public function store(CreateTaskRequest $request, UserStory $story): JsonResponse
    {
        $task = $this->service->create($request->validated(), $story, $request->user());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $task = $this->service->update($task, $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]));

        return new TaskResource($task);
    }

    public function changeStatus(ChangeStatusRequest $request, Task $task): TaskResource
    {
        $this->authorize('changeStatus', $task);

        $newStatus = TaskStatus::from($request->validated('status'));

        $task = $this->service->changeStatus($task, $newStatus, $request->user());

        return new TaskResource($task);
    }

    public function assign(Request $request, Task $task): TaskResource
    {
        $this->authorize('assign', $task);

        $request->validate(['assigned_to' => ['required', 'string', 'exists:users,id']]);

        $assignee = User::findOrFail($request->input('assigned_to'));

        $task = $this->service->assign($task, $assignee, $request->user());

        return new TaskResource($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $this->service->delete($task);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
