<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status->value,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'stories' => UserStoryResource::collection($this->whenLoaded('userStories')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
