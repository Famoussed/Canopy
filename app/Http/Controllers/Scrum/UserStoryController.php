<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scrum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\ChangeStatusRequest;
use App\Http\Requests\Scrum\CreateUserStoryRequest;
use App\Http\Requests\Scrum\EstimateStoryRequest;
use App\Http\Requests\Scrum\MoveToSprintRequest;
use App\Http\Requests\Scrum\ReorderBacklogRequest;
use App\Http\Requests\Scrum\UpdateUserStoryRequest;
use App\Http\Resources\UserStoryResource;
use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\UserStory;
use App\Services\UserStoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class UserStoryController extends Controller
{
    public function __construct(private readonly UserStoryService $service) {}

    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $query = $project->userStories()->with(['epic', 'creator']);

        if ($request->boolean('backlog')) {
            $query->backlog();
        }

        if ($request->has('sprint_id')) {
            $query->where('sprint_id', $request->string('sprint_id'));
        }

        if ($request->has('epic_id')) {
            $query->where('epic_id', $request->string('epic_id'));
        }

        if ($request->has('status')) {
            $statuses = explode(',', $request->string('status')->toString());
            $query->whereIn('status', $statuses);
        }

        $stories = $query->orderBy('order')->paginate($request->integer('per_page', 50));

        return UserStoryResource::collection($stories);
    }

    public function store(CreateUserStoryRequest $request, Project $project): JsonResponse
    {
        $story = $this->service->create($request->validated(), $project, $request->user());

        return (new UserStoryResource($story))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project, UserStory $story): UserStoryResource
    {
        return new UserStoryResource($story->load(['tasks', 'storyPoints', 'epic', 'creator', 'attachments']));
    }

    public function update(UpdateUserStoryRequest $request, Project $project, UserStory $story): UserStoryResource
    {
        $story = $this->service->update($story, $request->validated());

        return new UserStoryResource($story);
    }

    public function destroy(Project $project, UserStory $story): JsonResponse
    {
        $this->authorize('delete', $story);

        $this->service->delete($story);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function changeStatus(ChangeStatusRequest $request, Project $project, UserStory $story): UserStoryResource
    {
        $newStatus = StoryStatus::from($request->validated('status'));

        $story = $this->service->changeStatus($story, $newStatus, $request->user());

        return new UserStoryResource($story);
    }

    public function moveToSprint(MoveToSprintRequest $request, Project $project, UserStory $story): UserStoryResource
    {
        $sprint = Sprint::findOrFail($request->validated('sprint_id'));

        $story = $this->service->moveToSprint($story, $sprint, $request->user());

        return new UserStoryResource($story);
    }

    public function estimate(EstimateStoryRequest $request, Project $project, UserStory $story): UserStoryResource
    {
        $story = $this->service->estimate($story, $request->validated('points'));

        return new UserStoryResource($story);
    }

    public function reorder(ReorderBacklogRequest $request, Project $project): JsonResponse
    {
        $this->service->reorder($project, $request->validated('ordered_ids'));

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
