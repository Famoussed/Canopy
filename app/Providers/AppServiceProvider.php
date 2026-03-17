<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Scrum\StoryStatusChanged;
use App\Events\Scrum\TaskStatusChanged;
use App\Listeners\SendStatusChangeNotification;
use Illuminate\Database\Eloquent\Model;
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
        // Performance & safety guards (disabled in production)
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        Event::listen(StoryStatusChanged::class, SendStatusChangeNotification::class);

        Event::listen(TaskStatusChanged::class, SendStatusChangeNotification::class);
        Event::listen(\App\Events\Issue\IssueStatusChanged::class, SendStatusChangeNotification::class);

    }
}
