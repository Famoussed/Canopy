<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * DOCS 07 Section 14: WebSocket Channels
 */
Broadcast::channel('project.{projectId}', function (User $user, string $projectId) {
    return $user->projectMemberships()
        ->where('project_id', $projectId)
        ->exists();
});

Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
