<?php

declare(strict_types=1);

namespace Tests\Feature\File;

use App\Enums\ProjectRole;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F-11 & F-12: Dosya yükleme ve silme testleri.
 *
 * S3 disk fake'lenerek dosya yükleme ve silme iş akışını test eder.
 */
class AttachmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private UserStory $story;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
        $this->project->memberships()->create([
            'user_id' => $this->user->id,
            'role' => ProjectRole::Owner,
        ]);
        $this->story = UserStory::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_upload_file(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->create('design.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson('/api/attachments', [
            'attachable_type' => 'user_story',
            'attachable_id' => $this->story->id,
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attachments', [
            'filename' => 'design.pdf',
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_upload_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/attachments', []);

        $response->assertStatus(422);
    }

    public function test_upload_validates_file_size(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->create('huge.zip', 20480, 'application/zip'); // 20MB

        $response = $this->actingAs($this->user)->postJson('/api/attachments', [
            'attachable_type' => 'user_story',
            'attachable_id' => $this->story->id,
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_delete_attachment(): void
    {
        Storage::fake('s3');

        $attachment = Attachment::create([
            'attachable_type' => UserStory::class,
            'attachable_id' => $this->story->id,
            'filename' => 'test.pdf',
            'path' => 'attachments/test/test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/attachments/{$attachment->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $response = $this->postJson('/api/attachments', [
            'attachable_type' => 'user_story',
            'attachable_id' => $this->story->id,
        ]);

        $response->assertStatus(401);
    }
}
