<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\File\AttachmentController;
use App\Http\Controllers\Issue\IssueController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Project\MembershipController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Scrum\EpicController;
use App\Http\Controllers\Scrum\SprintController;
use App\Http\Controllers\Scrum\TaskController;
use App\Http\Controllers\Scrum\UserStoryController;
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
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
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
            Route::put('stories/reorder', [UserStoryController::class, 'reorder'])->name('stories.reorder');
            Route::apiResource('stories', UserStoryController::class);
            Route::put('stories/{story}/status', [UserStoryController::class, 'changeStatus'])->name('stories.change-status');
            Route::post('stories/{story}/move-to-sprint', [UserStoryController::class, 'moveToSprint'])->name('stories.move-to-sprint');
            Route::put('stories/{story}/estimate', [UserStoryController::class, 'estimate'])->name('stories.estimate');

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
    Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    });
});
