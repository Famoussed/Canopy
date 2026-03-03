<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AddMemberRequest;
use App\Http\Resources\MemberResource;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MembershipController extends Controller
{
    public function __construct(private MembershipService $service) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        $members = $project->memberships()->with('user')->get();

        return MemberResource::collection($members);
    }

    public function store(AddMemberRequest $request, Project $project): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();
        $role = ProjectRole::from($request->validated('role'));

        $membership = $this->service->add($project, $user, $role, $request->user());

        return (new MemberResource($membership->load('user')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Project $project, string $userId): JsonResponse
    {
        $this->authorize('removeMember', $project);

        $user = User::findOrFail($userId);

        $this->service->remove($project, $user, request()->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
