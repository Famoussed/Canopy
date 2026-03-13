<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        // Eager-loaded memberships kullanarak N+1 sorgusu önlenir
        $membership = null;
        if ($user && $this->relationLoaded('memberships')) {
            $membership = $this->memberships->firstWhere('user_id', $user->id);
        } elseif ($user) {
            $membership = $user->projectMemberships()->where('project_id', $this->id)->first();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'settings' => $this->when($membership !== null, $this->settings),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'member_count' => $this->whenCounted('members', $this->members_count),
            'my_role' => $membership?->role->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
