<?php

namespace App\Providers;

use App\Events\Project\MemberAdded;
use App\Events\Scrum\SprintClosed;
use App\Events\Scrum\StoryStatusChanged;
use App\Events\Scrum\TaskAssigned;
use App\Events\Scrum\TaskStatusChanged;
use App\Listeners\RecalculateEpicCompletion;
use App\Listeners\ReturnUnfinishedStoriesToBacklog;
use App\Listeners\SendMemberAddedNotification;
use App\Listeners\SendStatusChangeNotification;
use App\Listeners\SendTaskAssignedNotification;
use App\Listeners\UpdateBurndownSnapshot;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(StoryStatusChanged::class, RecalculateEpicCompletion::class);
        Event::listen(StoryStatusChanged::class, SendStatusChangeNotification::class);
        Event::listen(StoryStatusChanged::class, UpdateBurndownSnapshot::class);

        Event::listen(TaskStatusChanged::class, SendStatusChangeNotification::class);

        Event::listen(TaskAssigned::class, SendTaskAssignedNotification::class);

        Event::listen(MemberAdded::class, SendMemberAddedNotification::class);

        Event::listen(SprintClosed::class, ReturnUnfinishedStoriesToBacklog::class);
    }
}
