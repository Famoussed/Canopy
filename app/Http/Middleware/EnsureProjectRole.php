<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\ProjectRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectRole
{
    /**
     * Kullanıcının projede belirli bir minimum role sahip olduğunu doğrula.
     * EnsureProjectMember middleware'inden sonra çalışmalıdır.
     *
     * Kullanım: ->middleware('project.role:moderator')
     */
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $user = $request->user();

        // Super admin bypass
        if ($user?->isSuperAdmin()) {
            return $next($request);
        }

        $membership = $request->attributes->get('membership');

        if (! $membership) {
            abort(Response::HTTP_FORBIDDEN, 'Project membership not found.');
        }

        $requiredRole = ProjectRole::from($minimumRole);

        if (! $membership->role->isAtLeast($requiredRole)) {
            abort(Response::HTTP_FORBIDDEN, "Minimum role required: {$requiredRole->label()}");
        }

        return $next($request);
    }
}
