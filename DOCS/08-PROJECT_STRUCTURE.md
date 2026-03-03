# 08 — Project Structure

Laravel dizin yapısı, namespace-based organizasyon, Service/Action konvansiyonları ve dosya isimlendirme kuralları.

**İlişkili Dokümanlar:** [Architecture Overview](./01-ARCHITECTURE_OVERVIEW.md) | [Coding Standards](./14-CODING_STANDARDS.md)

---

## 1. Dizin Yapısı

```
project-root/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   └── RegisterController.php
│   │   │   ├── Project/
│   │   │   │   ├── ProjectController.php
│   │   │   │   └── MembershipController.php
│   │   │   ├── Scrum/
│   │   │   │   ├── EpicController.php
│   │   │   │   ├── UserStoryController.php
│   │   │   │   ├── TaskController.php
│   │   │   │   └── SprintController.php
│   │   │   ├── Issue/
│   │   │   │   └── IssueController.php
│   │   │   └── Analytics/
│   │   │       └── AnalyticsController.php
│   │   │
│   │   ├── Requests/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginRequest.php
│   │   │   │   └── RegisterRequest.php
│   │   │   ├── Project/
│   │   │   │   ├── CreateProjectRequest.php
│   │   │   │   ├── UpdateProjectRequest.php
│   │   │   │   └── AddMemberRequest.php
│   │   │   ├── Scrum/
│   │   │   │   ├── CreateEpicRequest.php
│   │   │   │   ├── CreateUserStoryRequest.php
│   │   │   │   ├── EstimateStoryRequest.php
│   │   │   │   ├── CreateSprintRequest.php
│   │   │   │   ├── CreateTaskRequest.php
│   │   │   │   ├── ChangeStatusRequest.php
│   │   │   │   └── MoveToSprintRequest.php
│   │   │   └── Issue/
│   │   │       ├── CreateIssueRequest.php
│   │   │       └── UpdateIssueRequest.php
│   │   │
│   │   ├── Resources/
│   │   │   ├── ProjectResource.php
│   │   │   ├── MemberResource.php
│   │   │   ├── EpicResource.php
│   │   │   ├── UserStoryResource.php
│   │   │   ├── SprintResource.php
│   │   │   ├── TaskResource.php
│   │   │   ├── IssueResource.php
│   │   │   ├── AttachmentResource.php
│   │   │   └── NotificationResource.php
│   │   │
│   │   └── Middleware/
│   │       ├── EnsureProjectMember.php
│   │       └── EnsureProjectRole.php
│   │
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── ProjectService.php
│   │   ├── MembershipService.php
│   │   ├── EpicService.php
│   │   ├── UserStoryService.php
│   │   ├── TaskService.php
│   │   ├── SprintService.php
│   │   ├── IssueService.php
│   │   ├── BurndownService.php
│   │   ├── VelocityService.php
│   │   ├── NotificationService.php
│   │   └── AttachmentService.php
│   │
│   ├── Actions/
│   │   ├── Auth/
│   │   │   ├── CreateUserAction.php
│   │   │   └── AuthenticateUserAction.php
│   │   ├── Project/
│   │   │   ├── CreateProjectAction.php
│   │   │   ├── AddMemberAction.php
│   │   │   ├── RemoveMemberAction.php
│   │   │   └── TransferOwnershipAction.php
│   │   ├── Scrum/
│   │   │   ├── CreateEpicAction.php
│   │   │   ├── CreateUserStoryAction.php
│   │   │   ├── MoveStoryToSprintAction.php
│   │   │   ├── DetectScopeChangeAction.php
│   │   │   ├── CalculateEpicCompletionAction.php
│   │   │   ├── CalculateStoryPointsAction.php
│   │   │   ├── ChangeStoryStatusAction.php
│   │   │   ├── ChangeTaskStatusAction.php
│   │   │   ├── ReorderBacklogAction.php
│   │   │   ├── StartSprintAction.php
│   │   │   └── CloseSprintAction.php
│   │   ├── Issue/
│   │   │   ├── CreateIssueAction.php
│   │   │   └── ChangeIssueStatusAction.php
│   │   ├── Analytics/
│   │   │   ├── CalculateBurndownAction.php
│   │   │   ├── CalculateVelocityAction.php
│   │   │   └── SnapshotDailyBurndownAction.php
│   │   ├── Notification/
│   │   │   ├── SendNotificationAction.php
│   │   │   └── MarkAsReadAction.php
│   │   └── File/
│   │       ├── UploadFileAction.php
│   │       └── DeleteFileAction.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Project.php
│   │   ├── ProjectMembership.php
│   │   ├── Epic.php
│   │   ├── UserStory.php
│   │   ├── StoryPoint.php
│   │   ├── Sprint.php
│   │   ├── SprintScopeChange.php
│   │   ├── Task.php
│   │   ├── Issue.php
│   │   ├── Attachment.php
│   │   ├── Notification.php
│   │   └── ActivityLog.php
│   │
│   ├── Enums/
│   │   ├── ProjectRole.php
│   │   ├── StoryStatus.php
│   │   ├── TaskStatus.php
│   │   ├── IssueStatus.php
│   │   ├── SprintStatus.php
│   │   ├── IssueType.php
│   │   ├── IssuePriority.php
│   │   └── IssueSeverity.php
│   │
│   ├── Events/
│   │   ├── Project/
│   │   │   ├── ProjectCreated.php
│   │   │   ├── MemberAdded.php
│   │   │   └── MemberRemoved.php
│   │   ├── Scrum/
│   │   │   ├── StoryStatusChanged.php
│   │   │   ├── StoryCreated.php
│   │   │   ├── SprintScopeChanged.php
│   │   │   ├── SprintStarted.php
│   │   │   ├── SprintClosed.php
│   │   │   ├── TaskStatusChanged.php
│   │   │   └── TaskAssigned.php
│   │   └── Issue/
│   │       ├── IssueCreated.php
│   │       └── IssueStatusChanged.php
│   │
│   ├── Listeners/
│   │   ├── RecalculateEpicCompletion.php
│   │   ├── UpdateBurndownSnapshot.php
│   │   ├── SendStatusChangeNotification.php
│   │   ├── SendTaskAssignedNotification.php
│   │   ├── SendMemberAddedNotification.php
│   │   ├── LogActivity.php
│   │   ├── ReturnUnfinishedStoriesToBacklog.php
│   │   └── BroadcastProjectUpdate.php
│   │
│   ├── Policies/
│   │   ├── ProjectPolicy.php
│   │   ├── EpicPolicy.php
│   │   ├── UserStoryPolicy.php
│   │   ├── TaskPolicy.php
│   │   ├── SprintPolicy.php
│   │   ├── IssuePolicy.php
│   │   └── AttachmentPolicy.php
│   │
│   ├── Livewire/
│   │   ├── Auth/
│   │   │   ├── LoginForm.php
│   │   │   └── RegisterForm.php
│   │   ├── Project/
│   │   │   ├── ProjectList.php
│   │   │   ├── ProjectSettings.php
│   │   │   └── MemberManager.php
│   │   ├── Scrum/
│   │   │   ├── Backlog.php
│   │   │   ├── SprintBoard.php
│   │   │   ├── TaskBoard.php
│   │   │   └── EpicList.php
│   │   ├── Issue/
│   │   │   ├── IssueList.php
│   │   │   ├── IssueDetail.php
│   │   │   └── IssueForm.php
│   │   ├── Analytics/
│   │   │   ├── BurndownChart.php
│   │   │   └── VelocityChart.php
│   │   └── Notification/
│   │       ├── NotificationPanel.php
│   │       └── NotificationBell.php
│   │
│   ├── Traits/
│   │   ├── HasStateMachine.php
│   │   ├── BelongsToProject.php
│   │   └── Auditable.php
│   │
│   ├── Exceptions/
│   │   ├── InvalidStatusTransitionException.php
│   │   ├── TaskNotAssignedException.php
│   │   ├── ActiveSprintAlreadyExistsException.php
│   │   ├── DuplicateMemberException.php
│   │   └── OwnerCannotBeRemovedException.php
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       ├── AuthServiceProvider.php
│       └── EventServiceProvider.php
│
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── UserSeeder.php
│   │   ├── ProjectSeeder.php
│   │   └── ...
│   └── factories/
│       ├── UserFactory.php
│       ├── ProjectFactory.php
│       ├── UserStoryFactory.php
│       └── ...
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   ├── Project/
│   │   ├── Scrum/
│   │   ├── Issue/
│   │   └── Rbac/
│   ├── Unit/
│   │   ├── Services/
│   │   └── Actions/
│   └── Livewire/
│
├── resources/
│   └── views/
│       ├── layouts/
│       ├── livewire/
│       │   ├── auth/
│       │   ├── project/
│       │   ├── scrum/
│       │   ├── issue/
│       │   ├── analytics/
│       │   └── notification/
│       └── components/
│
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── supervisord.conf
│
├── docker-compose.yml
├── .env.example
└── ...
```

---

## 2. Katman Konvansiyonları

### 2.1 Controller

**Konum:** `app/Http/Controllers/{Domain}/`
**İsimlendirme:** `{Entity}Controller.php` (PascalCase)
**Kurallar:**
- Sadece Service çağırır
- Request alır, response döner
- 5-10 satır max per method
- `Model::create()` YASAK
- Business logic YASAK

```php
// ✅ Doğru
class UserStoryController extends Controller
{
    public function __construct(private UserStoryService $service) {}

    public function store(CreateUserStoryRequest $request, Project $project)
    {
        $story = $this->service->create($request->validated(), $project, $request->user());
        return new UserStoryResource($story);
    }
}

// ❌ Yanlış
class UserStoryController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([...]); // ❌ inline validation
        $story = UserStory::create($validated);  // ❌ direkt model
        return response()->json($story);          // ❌ resource yok
    }
}
```

### 2.2 Service

**Konum:** `app/Services/`
**İsimlendirme:** `{Entity}Service.php` (PascalCase)
**Kurallar:**
- İş mantığı ve orkestrasyon burada
- `DB::transaction()` burada yönetilir
- Action'ları koordine eder
- Event dispatch burada yapılır
- Birden fazla Action çağırabilir

```php
class SprintService
{
    public function __construct(
        private StartSprintAction $startAction,
        private CloseSprintAction $closeAction,
    ) {}

    public function start(Sprint $sprint, User $user): Sprint
    {
        return DB::transaction(function () use ($sprint, $user) {
            $sprint = $this->startAction->execute($sprint);
            SprintStarted::dispatch($sprint, $user);
            return $sprint;
        });
    }
}
```

### 2.3 Action

**Konum:** `app/Actions/{Domain}/`
**İsimlendirme:** `{Verb}{Entity}Action.php` (PascalCase)
**Kurallar:**
- Tek `execute()` metodu
- Tek sorumluluk
- Transaction yönetmez (Service yapar)
- Event fırlatmaz (Service yapar)
- Yeniden kullanılabilir

```php
class StartSprintAction
{
    public function execute(Sprint $sprint): Sprint
    {
        // İş kuralı: Aynı anda 1 aktif sprint
        $hasActive = Sprint::where('project_id', $sprint->project_id)
            ->where('status', SprintStatus::Active)
            ->exists();

        if ($hasActive) {
            throw new ActiveSprintAlreadyExistsException();
        }

        $sprint->update([
            'status' => SprintStatus::Active,
            'start_date' => now()->toDateString(),
        ]);

        return $sprint->fresh();
    }
}
```

### 2.4 FormRequest

**Konum:** `app/Http/Requests/{Domain}/`
**İsimlendirme:** `{Verb}{Entity}Request.php`
**Kurallar:**
- Tüm validation burada
- `authorize()` ile Policy çağrılabilir
- Controller'da inline validation YASAK

### 2.5 Policy

**Konum:** `app/Policies/`
**İsimlendirme:** `{Entity}Policy.php`
**Kurallar:**
- `before()` ile super admin bypass
- Her method `bool` döner
- Manuel ID karşılaştırması YASAK → `ProjectRole` enum hiyerarşisi kullan

### 2.6 Livewire Component

**Konum:** `app/Livewire/{Domain}/`
**İsimlendirme:** `{Feature}.php` (PascalCase)
**Kurallar:**
- Controller gibi davranır: Service çağırır
- Business logic YASAK
- Validation → Service'e delege et veya FormRequest kullan
- State management ve UI event handling burada

```php
// ✅ Doğru
class SprintBoard extends Component
{
    public function changeStoryStatus(string $storyId, string $newStatus)
    {
        $story = UserStory::findOrFail($storyId);
        $this->authorize('changeStatus', $story);

        $this->userStoryService->changeStatus($story, $newStatus, auth()->user());
    }
}

// ❌ Yanlış
class SprintBoard extends Component
{
    public function changeStoryStatus(string $storyId, string $newStatus)
    {
        $story = UserStory::findOrFail($storyId);
        $story->update(['status' => $newStatus]); // ❌ direkt model update
        StoryStatusChanged::dispatch($story);       // ❌ event dispatch component'te
    }
}
```

---

## 3. İsimlendirme Kuralları

| Öğe | Format | Örnek |
|-----|--------|-------|
| Controller | PascalCase + Controller | `UserStoryController` |
| Service | PascalCase + Service | `UserStoryService` |
| Action | Verb + Entity + Action | `CreateUserStoryAction` |
| Model | PascalCase (tekil) | `UserStory` |
| Enum | PascalCase | `ProjectRole`, `StoryStatus` |
| Event | PascalCase (geçmiş zaman) | `StoryStatusChanged` |
| Listener | PascalCase (eylem) | `RecalculateEpicCompletion` |
| Policy | PascalCase + Policy | `UserStoryPolicy` |
| FormRequest | Verb + Entity + Request | `CreateUserStoryRequest` |
| Resource | PascalCase + Resource | `UserStoryResource` |
| Trait | PascalCase (Has/Is prefix) | `HasStateMachine` |
| Exception | PascalCase + Exception | `InvalidStatusTransitionException` |
| Migration | snake_case (Laravel default) | `create_user_stories_table` |
| Factory | PascalCase + Factory | `UserStoryFactory` |
| Test | PascalCase + Test | `SprintWorkflowTest` |

---

## 4. Namespace Yapısı

```
App\Http\Controllers\Auth\         → Auth controller'ları
App\Http\Controllers\Project\      → Project controller'ları
App\Http\Controllers\Scrum\        → Scrum controller'ları
App\Http\Controllers\Issue\        → Issue controller'ları
App\Http\Controllers\Analytics\    → Analytics controller'ları

App\Http\Requests\Auth\            → Auth validation
App\Http\Requests\Project\         → Project validation
App\Http\Requests\Scrum\           → Scrum validation
App\Http\Requests\Issue\           → Issue validation

App\Services\                      → Tüm servisler (flat)
App\Actions\Auth\                  → Auth action'ları
App\Actions\Project\               → Project action'ları
App\Actions\Scrum\                 → Scrum action'ları
App\Actions\Issue\                 → Issue action'ları
App\Actions\Analytics\             → Analytics action'ları
App\Actions\Notification\          → Notification action'ları
App\Actions\File\                  → File action'ları

App\Models\                        → Tüm model'ler (flat)
App\Enums\                         → Tüm enum'lar (flat)
App\Events\{Domain}\               → Domain event'ler
App\Listeners\                     → Tüm listener'lar (flat)
App\Policies\                      → Tüm policy'ler (flat)
App\Livewire\{Domain}\             → Livewire component'ler
App\Traits\                        → Paylaşılan trait'ler
App\Exceptions\                    → Custom exception'lar
```

**Neden Services ve Models flat?**
- Service'ler cross-domain çağrı yapabilir (`SprintService` → `NotificationService`)
- Model'ler cross-relation tanımlar (`UserStory` → `Sprint`, `Epic`, `Project`)
- Flat yapı import yollarını kısa tutar

**Neden Actions grouped?**
- Action'lar domain-specific, cross-domain çağrı az
- Gruplandırma dosya bulmayı kolaylaştırır (20+ action dosyası)

---

**Önceki:** [07-API_DESIGN.md](./07-API_DESIGN.md)
**Sonraki:** [09-INFRASTRUCTURE.md](./09-INFRASTRUCTURE.md)
