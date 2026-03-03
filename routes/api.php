<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Issue\IssueController;
use App\Http\Controllers\Project\MembershipController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Scrum\EpicController;
use App\Http\Controllers\Scrum\SprintController;
use App\Http\Controllers\Scrum\TaskController;
use App\Http\Controllers\Scrum\UserStoryController;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', RegisterController::class)->name('auth.register');
    Route::post('/login', LoginController::class)->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', function (Request $request) {
            $request->user()->tokens()->delete();

            return response()->json(null, 204);
        })->name('auth.logout');

        Route::get('/me', function (Request $request) {
            return new UserResource($request->user());
        })->name('auth.me');
    });
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Projects
    |--------------------------------------------------------------------------
    */
    Route::apiResource('projects', ProjectController::class)
        ->scoped(['project' => 'slug']);

    /*
    |--------------------------------------------------------------------------
    | Project-scoped routes (membership required)
    |--------------------------------------------------------------------------
    */
    Route::prefix('projects/{project:slug}')
        ->middleware('project.member')
        ->group(function () {

            // Members
            Route::get('/members', [MembershipController::class, 'index'])->name('projects.members.index');
            Route::post('/members', [MembershipController::class, 'store'])->name('projects.members.store');
            Route::delete('/members/{userId}', [MembershipController::class, 'destroy'])->name('projects.members.destroy');

            // Epics
            Route::apiResource('epics', EpicController::class);

            // User Stories
            Route::apiResource('stories', UserStoryController::class);
            Route::put('stories/{story}/status', [UserStoryController::class, 'changeStatus'])->name('stories.change-status');
            Route::post('stories/{story}/move-to-sprint', [UserStoryController::class, 'moveToSprint'])->name('stories.move-to-sprint');
            Route::put('stories/{story}/estimate', [UserStoryController::class, 'estimate'])->name('stories.estimate');
            Route::put('stories/reorder', [UserStoryController::class, 'reorder'])->name('stories.reorder');

            // Sprints
            Route::apiResource('sprints', SprintController::class);
            Route::post('sprints/{sprint}/start', [SprintController::class, 'start'])->name('sprints.start');
            Route::post('sprints/{sprint}/close', [SprintController::class, 'close'])->name('sprints.close');

            // Issues
            Route::apiResource('issues', IssueController::class);
            Route::put('issues/{issue}/status', [IssueController::class, 'changeStatus'])->name('issues.change-status');

            // Analytics
            Route::get('sprints/{sprint}/burndown', [AnalyticsController::class, 'burndown'])->name('sprints.burndown');
            Route::get('velocity', [AnalyticsController::class, 'velocity'])->name('projects.velocity');
        });

    /*
    |--------------------------------------------------------------------------
    | Task Routes (story-scoped)
    |--------------------------------------------------------------------------
    */
    Route::get('stories/{story}/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('stories/{story}/tasks', [TaskController::class, 'store'])->name('tasks.store');

    Route::prefix('tasks')->group(function () {
        Route::put('{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::put('{task}/status', [TaskController::class, 'changeStatus'])->name('tasks.change-status');
        Route::put('{task}/assign', [TaskController::class, 'assign'])->name('tasks.assign');
        Route::delete('{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */
    Route::post('attachments', function (Request $request) {
        $request->validate([
            'attachable_type' => ['required', 'string', 'in:user_story,task,issue'],
            'attachable_id' => ['required', 'string'],
            'file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        $modelMap = [
            'user_story' => \App\Models\UserStory::class,
            'task' => \App\Models\Task::class,
            'issue' => \App\Models\Issue::class,
        ];

        $model = $modelMap[$request->input('attachable_type')]::findOrFail($request->input('attachable_id'));

        $attachment = app(\App\Services\AttachmentService::class)->upload(
            $request->file('file'),
            $model,
            $request->user()
        );

        return new \App\Http\Resources\AttachmentResource($attachment);
    })->name('attachments.store');

    Route::delete('attachments/{attachment}', function (\App\Models\Attachment $attachment, Request $request) {
        app(\App\Services\AttachmentService::class)->delete($attachment);

        return response()->json(null, 204);
    })->name('attachments.destroy');

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', function (Request $request) {
            $notifications = $request->user()
                ->notifications()
                ->unread()
                ->latest()
                ->paginate(20);

            return NotificationResource::collection($notifications)
                ->additional(['meta' => ['unread_count' => $request->user()->notifications()->unread()->count()]]);
        })->name('notifications.index');

        Route::post('/mark-read', function (Request $request) {
            $request->validate(['id' => ['required', 'string']]);

            app(\App\Services\NotificationService::class)->markAsRead($request->input('id'), $request->user());

            return response()->json(null, 204);
        })->name('notifications.mark-read');

        Route::post('/mark-all-read', function (Request $request) {
            app(\App\Services\NotificationService::class)->markAllAsRead($request->user());

            return response()->json(null, 204);
        })->name('notifications.mark-all-read');
    });
});
