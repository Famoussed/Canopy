<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scrum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\CreateSprintRequest;
use App\Http\Requests\Scrum\UpdateSprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\SprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SprintController extends Controller
{
    public function __construct(private readonly SprintService $service) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        return SprintResource::collection(
            $project->sprints()->withCount('userStories')->latest()->paginate(request()->integer('per_page', 20))
    public function store(CreateSprintRequest $request, Project $project): JsonResponse
    {
        $sprint = $this->service->create($request->validated(), $project);

        return (new SprintResource($sprint))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project, Sprint $sprint): SprintResource
    {
        return new SprintResource($sprint->load('userStories'));
    }

    public function update(UpdateSprintRequest $request, Project $project, Sprint $sprint): SprintResource
    {
        $sprint = $this->service->update($sprint, $request->validated());

        return new SprintResource($sprint);
    }

    public function destroy(Project $project, Sprint $sprint): JsonResponse
    {
        $this->authorize('delete', $sprint);

        $this->service->delete($sprint);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function start(Project $project, Sprint $sprint): SprintResource
    {
        $this->authorize('manage', $sprint);

        $sprint = $this->service->start($sprint, request()->user());

        return new SprintResource($sprint);
    }

    public function close(Project $project, Sprint $sprint): SprintResource
    {
        $this->authorize('manage', $sprint);

        $sprint = $this->service->close($sprint, request()->user());

        return new SprintResource($sprint);
    }
}
