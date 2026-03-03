<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectMember
{
    /**
     * Route'ta {project} parametresi varsa, kullanıcının o projenin üyesi olduğunu doğrula.
     * Membership bilgisini request'e ekleyerek sonraki katmanlarda tekrar sorgulanmasını önle.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if (! $project) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $membership = $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first();

        if (! $membership) {
            abort(Response::HTTP_FORBIDDEN, 'You are not a member of this project.');
        }

        // Membership'i request'e ekle — Controller ve Policy'lerde tekrar sorgu yapılmaz
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
