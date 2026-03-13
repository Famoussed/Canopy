<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\CreateIssueRequest;
use App\Http\Requests\Issue\UpdateIssueRequest;
use App\Http\Requests\Scrum\ChangeStatusRequest;
use App\Http\Resources\IssueResource;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Services\IssueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class IssueController extends Controller
{
    public function __construct(private readonly IssueService $service) {}

    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $query = $project->issues()->with(['creator', 'assignee']);

        if ($request->has('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->string('priority'));
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->string('severity'));
        }

        if ($request->has('status')) {
            $statuses = explode(',', $request->string('status')->toString());
            $query->whereIn('status', $statuses);
        }

        if ($request->string('assigned_to') === 'me') {
            $query->where('assigned_to', $request->user()->id);
        }

        $issues = $query->latest()->paginate($request->integer('per_page', 20));

        return IssueResource::collection($issues);
    }

    public function store(CreateIssueRequest $request, Project $project): JsonResponse
    {
        $issue = $this->service->create($request->validated(), $project, $request->user());

        return (new IssueResource($issue))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project, Issue $issue): IssueResource
    {
        return new IssueResource($issue->load(['creator', 'assignee', 'attachments']));
    }

    public function update(UpdateIssueRequest $request, Project $project, Issue $issue): IssueResource
    {
        $issue = $this->service->update($issue, $request->validated());

        return new IssueResource($issue);
    }

    public function destroy(Project $project, Issue $issue): JsonResponse
    {
        $this->authorize('delete', $issue);

        $this->service->delete($issue);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function changeStatus(ChangeStatusRequest $request, Project $project, Issue $issue): IssueResource
    {
        $this->authorize('changeStatus', $issue);

        $newStatus = IssueStatus::from($request->validated('status'));

        $issue = $this->service->changeStatus($issue, $newStatus, $request->user());

        return new IssueResource($issue);
    }
}
