<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'order' => $this->order,
            'total_points' => $this->total_points,
            'epic' => new EpicResource($this->whenLoaded('epic')),
            'sprint_id' => $this->sprint_id,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'story_points' => $this->whenLoaded('storyPoints', fn () => $this->storyPoints->map(fn ($sp) => [
                'role_name' => $sp->role_name,
                'points' => (float) $sp->points,
            ])),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
