<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scrum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\CreateEpicRequest;
use App\Http\Requests\Scrum\UpdateEpicRequest;
use App\Http\Resources\EpicResource;
use App\Models\Epic;
use App\Models\Project;
use App\Services\EpicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class EpicController extends Controller
{
    public function __construct(private readonly EpicService $service) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        $epics = $project->epics()->withCount('userStories')->get();

        return EpicResource::collection($epics);
    }

    public function store(CreateEpicRequest $request, Project $project): JsonResponse
    {
        $epic = $this->service->create($request->validated(), $project, $request->user());

        return (new EpicResource($epic))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project, Epic $epic): EpicResource
    {
        return new EpicResource($epic->load('userStories'));
    }

    public function update(UpdateEpicRequest $request, Project $project, Epic $epic): EpicResource
    {
        $epic = $this->service->update($epic, $request->validated());

        return new EpicResource($epic);
    }

    public function destroy(Project $project, Epic $epic): JsonResponse
    {
        $this->authorize('delete', $epic);

        $this->service->delete($epic);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
