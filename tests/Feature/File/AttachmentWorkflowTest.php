<?php

declare(strict_types=1);

namespace Tests\Feature\File;

use App\Enums\ProjectRole;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F-11 & F-12: Dosya yükleme ve silme testleri.
 *
 * S3 disk fake'lenerek dosya yükleme ve silme iş akışını AttachmentService
 * üzerinden test eder.
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

        $service = app(AttachmentService::class);
        $attachment = $service->upload($file, $this->story, $this->user);

        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_upload_to_target_user_story(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->create('doc.pdf', 512, 'application/pdf');

        $service = app(AttachmentService::class);
        $attachment = $service->uploadToTarget('user_story', $this->story->id, $file, $this->user);

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => UserStory::class,
            'attachable_id' => $this->story->id,
            'uploaded_by' => $this->user->id,
        ]);
    }

    public function test_upload_to_target_with_invalid_type_throws_exception(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $service = app(AttachmentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->uploadToTarget('invalid_type', $this->story->id, $file, $this->user);
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

        $service = app(AttachmentService::class);
        $service->delete($attachment);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }
}
