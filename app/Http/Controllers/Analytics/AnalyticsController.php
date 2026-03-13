<?php

declare(strict_types=1);

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\BurndownService;
use App\Services\VelocityService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly BurndownService $burndownService,
        private readonly VelocityService $velocityService,
    ) {}

    public function burndown(Project $project, Sprint $sprint): JsonResponse
    {
        $data = $this->burndownService->getBurndownData($sprint);

        return response()->json(['data' => $data]);
    }

    public function velocity(Project $project): JsonResponse
    {
        $data = $this->velocityService->getVelocityData($project);

        return response()->json(['data' => $data]);
    }
}
