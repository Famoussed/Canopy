<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\CreateProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::forUser($request->user()->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($projects);
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $project = $this->service->create($request->validated(), $request->user());

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load('owner'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project = $this->service->update($project, $request->validated());

        return new ProjectResource($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $this->service->delete($project);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
