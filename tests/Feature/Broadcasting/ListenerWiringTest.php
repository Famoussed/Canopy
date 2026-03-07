<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

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
use Tests\TestCase;

/**
 * Event→Listener eşlemelerinin doğru yapıldığını doğrulayan testler.
 */
class ListenerWiringTest extends TestCase
{
    public function test_story_status_changed_has_correct_listeners(): void
    {
        $listeners = Event::getListeners(StoryStatusChanged::class);

        $listenerClasses = $this->resolveListenerClasses($listeners);

        $this->assertContains(RecalculateEpicCompletion::class, $listenerClasses);
        $this->assertContains(SendStatusChangeNotification::class, $listenerClasses);
        $this->assertContains(UpdateBurndownSnapshot::class, $listenerClasses);
    }

    public function test_task_status_changed_has_correct_listeners(): void
    {
        $listeners = Event::getListeners(TaskStatusChanged::class);

        $listenerClasses = $this->resolveListenerClasses($listeners);

        $this->assertContains(SendStatusChangeNotification::class, $listenerClasses);
    }

    public function test_task_assigned_has_correct_listeners(): void
    {
        $listeners = Event::getListeners(TaskAssigned::class);

        $listenerClasses = $this->resolveListenerClasses($listeners);

        $this->assertContains(SendTaskAssignedNotification::class, $listenerClasses);
    }

    public function test_member_added_has_correct_listeners(): void
    {
        $listeners = Event::getListeners(MemberAdded::class);

        $listenerClasses = $this->resolveListenerClasses($listeners);

        $this->assertContains(SendMemberAddedNotification::class, $listenerClasses);
    }

    public function test_sprint_closed_has_correct_listeners(): void
    {
        $listeners = Event::getListeners(SprintClosed::class);

        $listenerClasses = $this->resolveListenerClasses($listeners);

        $this->assertContains(ReturnUnfinishedStoriesToBacklog::class, $listenerClasses);
    }

    /**
     * Event::getListeners() Closure dizisi döndürür.
     * Closure'un use() parametrelerini okuyarak listener class'larını belirler.
     *
     * @param  array<\Closure>  $listeners
     * @return array<string>
     */
    private function resolveListenerClasses(array $listeners): array
    {
        $classes = [];

        foreach ($listeners as $listener) {
            $reflection = new \ReflectionFunction($listener);
            $vars = $reflection->getStaticVariables();

            if (isset($vars['listener'])) {
                $classes[] = $vars['listener'];
            }
        }

        return $classes;
    }
}
