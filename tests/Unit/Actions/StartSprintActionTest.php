<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Scrum\StartSprintAction;
use App\Enums\SprintStatus;
use App\Exceptions\ActiveSprintAlreadyExistsException;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint Başlatma Testi (StartSprintAction)
 *
 * Bu test sınıfı, "Planning" aşamasındaki bir Sprint'in başlatılması
 * (Active durumuna geçirilmesi) sürecindeki iş kurallarını doğrular.
 * Özellikle aynı projede birden fazla aktif Sprint olmaması kuralını test eder.
 *
 * Kullanılan Action: StartSprintAction
 * Bağımlılıklar: Project modeli, Sprint modeli, SprintStatus enum
 * İlgili Exception: ActiveSprintAlreadyExistsException
 *
 * Test Edilen Senaryolar:
 *  - test_can_start_planning_sprint:
 *    "Planning" durumundaki bir Sprint oluşturulur ve başlatılır.
 *    Sprint durumunun "Active" olması ve start_date alanının bugünün
 *    tarihi ile doldurulması beklenir.
 *
 *  - test_cannot_start_when_active_sprint_exists:
 *    Projede zaten aktif bir Sprint varken ikinci bir Sprint başlatılmaya
 *    çalışılır. ActiveSprintAlreadyExistsException fırlatılması beklenir.
 *    (İş Kuralı: Bir projede aynı anda yalnızca bir aktif Sprint olabilir.)
 */
class StartSprintActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_start_planning_sprint(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'status' => SprintStatus::Planning,
        ]);

        $action = new StartSprintAction;
        $result = $action->execute($sprint);

        $this->assertEquals(SprintStatus::Active, $result->status);
        $this->assertEquals(now()->toDateString(), $result->start_date->toDateString());
    }

    public function test_cannot_start_when_active_sprint_exists(): void
    {
        $project = Project::factory()->create();
        Sprint::factory()->active()->create(['project_id' => $project->id]);
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'status' => SprintStatus::Planning,
        ]);

        $this->expectException(ActiveSprintAlreadyExistsException::class);

        $action = new StartSprintAction;
        $action->execute($sprint);
    }
}
