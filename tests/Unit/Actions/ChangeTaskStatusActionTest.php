<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\ChangeTaskStatusAction;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotAssignedException;
use App\Models\Task;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task Durum Değişikliği Testi (ChangeTaskStatusAction)
 *
 * Bu test sınıfı, bir Task'ın durumunun (status) değiştirilmesi sırasında
 * uygulanan iş kurallarını doğrular. Özellikle atanmamış görevlerin
 * başlatılamaması kuralını ve atanmış görevlerin sorunsuz başlayabilmesini test eder.
 *
 * Kullanılan Action: ChangeTaskStatusAction
 * Bağımlılıklar: Task modeli, User modeli, UserStory modeli, TaskStatus enum
 * İlgili Exception: TaskNotAssignedException
 *
 * Test Edilen Senaryolar:
 *  - test_cannot_start_unassigned_task:
 *    Kimseye atanmamış (assigned_to = null) bir Task oluşturulur ve
 *    durumu "InProgress"a çekilmeye çalışılır. TaskNotAssignedException
 *    fırlatılması beklenir. (İş Kuralı: Atanmamış görev başlatılamaz.)
 *
 *  - test_assigned_task_can_start:
 *    Bir kullanıcıya atanmış Task oluşturulur ve durumu "InProgress"a çekilir.
 *    Task durumunun başarıyla InProgress'e geçmesi beklenir.
 */
class ChangeTaskStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_start_unassigned_task(): void
    {
        $story = UserStory::factory()->create();
        $task = Task::factory()->create([
            'user_story_id' => $story->id,
            'status' => TaskStatus::New,
            'assigned_to' => null,
        ]);

        $this->expectException(TaskNotAssignedException::class);

        $action = new ChangeTaskStatusAction();
        $action->execute($task, TaskStatus::InProgress);
    }

    public function test_assigned_task_can_start(): void
    {
        $user = User::factory()->create();
        $story = UserStory::factory()->create();
        $task = Task::factory()->create([
            'user_story_id' => $story->id,
            'status' => TaskStatus::New,
            'assigned_to' => $user->id,
        ]);

        $action = new ChangeTaskStatusAction();
        $result = $action->execute($task, TaskStatus::InProgress);

        $this->assertEquals(TaskStatus::InProgress, $result->status);
    }
}
