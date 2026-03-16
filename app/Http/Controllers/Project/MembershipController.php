<?php

declare(strict_types=1);

namespace App\Http\Controllers\Project;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\AddMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Project;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $service) {}

    public function index(Project $project): AnonymousResourceCollection
    {
        $members = $this->service->getProjectMembers($project);

        return MemberResource::collection($members);
    }

    public function store(AddMemberRequest $request, Project $project): JsonResponse
    {
        $role = ProjectRole::from($request->validated('role'));

        try {
            $membership = $this->service->addByEmail(
                $project,
                $request->validated('email'),
                $role,
                $request->user()
            );
        } catch (\App\Exceptions\MaxMembersExceededException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\App\Exceptions\DuplicateMemberException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new MemberResource($membership->load('user')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Project $project, string $userId): JsonResponse
    {
        $this->authorize('removeMember', $project);

        $this->service->removeById($project, $userId, request()->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
