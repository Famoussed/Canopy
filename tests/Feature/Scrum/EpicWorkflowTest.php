<?php

declare(strict_types=1);

namespace Tests\Feature\Scrum;

use App\Models\Epic;
use App\Models\Project;
use App\Models\User;
use App\Services\EpicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Epic Workflow Testleri
 *
 * Bu test sınıfı Epic kayıtlarının temel CRUD işlemlerini doğrular.
 * Epic nesneleri proje hiyerarşisinin en üstünde yer aldığı için
 * alt nesnelerin (UserStory) değişimlerinden de etkilenir.
 *
 * Test Edilen Senaryolar:
 * - test_user_can_create_epic: Yeni Epic oluşturulması.
 * - test_user_can_update_epic: Mevcut Epic'in ayarlarının güncellenmesi.
 * - test_user_can_delete_epic: Epic silinmesi.
 */
class EpicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected EpicService $epicService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->epicService = app(EpicService::class);
    }

    public function test_user_can_create_epic(): void
    {
        $data = [
            'title' => 'Core Authorization',
            'description' => 'User login, register algorithms.',
            'color' => '#FF0000',
        ];

        $epic = $this->epicService->create($data, $this->project, $this->user);

        $this->assertInstanceOf(Epic::class, $epic);
        $this->assertDatabaseHas('epics', [
            'id' => $epic->id,
            'title' => 'Core Authorization',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_user_can_update_epic(): void
    {
        $epic = Epic::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Initial Title',
            'color' => '#000000',
        ]);

        $updatedEpic = $this->epicService->update($epic, [
            'title' => 'Updated Title',
            'color' => '#FFFFFF',
        ]);

        $this->assertEquals('Updated Title', $updatedEpic->title);
        $this->assertEquals('#FFFFFF', $updatedEpic->color);
        $this->assertDatabaseHas('epics', [
            'id' => $epic->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_user_can_delete_epic(): void
    {
        $epic = Epic::factory()->create(['project_id' => $this->project->id]);

        $this->epicService->delete($epic);

        $this->assertDatabaseMissing('epics', ['id' => $epic->id]);
    }
}
