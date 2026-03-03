<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpicResource extends JsonResource
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
            'color' => $this->color,
            'status' => $this->status?->value,
            'completion_percentage' => $this->completion_percentage,
            'stories_count' => $this->whenCounted('userStories', $this->user_stories_count),
            'stories' => UserStoryResource::collection($this->whenLoaded('userStories')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
