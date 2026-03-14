# Canopy — Event-Listeners & Livewire Mimari Raporu

> **Hazırlanma Tarihi:** 2026-03-14
> **Kapsam:** Event–Listener yapısının gereklilik analizi ve detaylı haritası + Livewire 4 (Volt) mimari raporu

---

## İÇİNDEKİLER

- [BÖLÜM 1 — Event-Listeners Yapısı](#bölüm-1--event-listeners-yapısı)
  - [1.1 Event-Listener Yapısı Gerçekten Gerekli miydi?](#11-event-listener-yapısı-gerçekten-gerekli-miydi)
  - [1.2 Neden Gerekli?](#12-neden-gerekli)
  - [1.3 Tüm Event Sınıfları — Detaylı Envanter](#13-tüm-event-sınıfları--detaylı-envanter)
  - [1.4 Tüm Listener Sınıfları — Detaylı Envanter](#14-tüm-listener-sınıfları--detaylı-envanter)
  - [1.5 Event Kayıt Haritası (Wiring)](#15-event-kayıt-haritası-wiring)
  - [1.6 Event Dispatch Noktaları — Hangi Service Nereden Dispatch Ediyor?](#16-event-dispatch-noktaları--hangi-service-nereden-dispatch-ediyor)
  - [1.7 Broadcast Kanalları](#17-broadcast-kanalları)
  - [1.8 Livewire Tarafındaki Echo Listener'ları](#18-livewire-tarafındaki-echo-listenerları)
  - [1.9 Event Akış Diyagramları](#19-event-akış-diyagramları)
  - [1.10 Yetim (Orphan) Event & Listener Analizi](#110-yetim-orphan-event--listener-analizi)
  - [1.11 Refactoring Önerileri](#111-refactoring-önerileri)
- [BÖLÜM 2 — Livewire 4 Özellikleri & Component Mimarisi](#bölüm-2--livewire-4-özellikleri--component-mimarisi)
  - [2.1 Livewire 4 vs Livewire 3 — Bu Projede Kullanılan Farklar](#21-livewire-4-vs-livewire-3--bu-projede-kullanılan-farklar)
  - [2.2 Volt (Single-File Components) Mimarisi](#22-volt-single-file-components-mimarisi)
  - [2.3 Component Envanteri — 14 Bileşenin Detaylı Dökümü](#23-component-envanteri--14-bileşenin-detaylı-dökümü)
  - [2.4 Routing Yapısı (`Route::livewire`)](#24-routing-yapısı-routelivewire)
  - [2.5 Ortak Kullanılan Livewire 4 Pattern'leri](#25-ortak-kullanılan-livewire-4-patternleri)
  - [2.6 Real-time Entegrasyon Mimarisi](#26-real-time-entegrasyon-mimarisi)
  - [2.7 Flux UI Entegrasyonu](#27-flux-ui-entegrasyonu)
  - [2.8 Livewire Component Mimarisi Özet Tablosu](#28-livewire-component-mimarisi-özet-tablosu)

---

# BÖLÜM 1 — Event-Listeners Yapısı

## 1.1 Event-Listener Yapısı Gerçekten Gerekli miydi?

**Evet, kesinlikle gerekliydi.** Canopy projesi bir Scrum yönetim aracı olup aşağıdaki özelliklere sahiptir:

1. **Real-time güncellemeler** — Kanban board, backlog, sprint listesi gibi ekranların birden fazla kullanıcı arasında anlık senkronize olması gerekiyor.
2. **Bildirim sistemi** — Story/Task durum değişikliklerinde, task atamalarında ve proje üye eklemelerinde ilgili kullanıcılara bildirim gönderiliyor.
3. **Analitik hesaplamalar** — Story durumu değiştiğinde burndown snapshot'ı alınıyor ve epic tamamlanma oranı yeniden hesaplanıyor.
4. **Domain mantığı tetikleme** — Sprint kapatıldığında tamamlanmamış story'ler otomatik olarak backlog'a geri döndürülüyor.

Bu ihtiyaçlardan herhangi biri bile event-driven mimariyi haklı kılmaya yeter. Canopy'de **hepsi** bir arada kullanılmaktadır.

## 1.2 Neden Gerekli?

| Gerekçe | Açıklama |
|---------|----------|
| **Loose Coupling (Gevşek Bağlılık)** | Service katmanı, bildirim/analiz/broadcast gibi yan etkileri bilmek zorunda değil. `StoryStatusChanged` dispatch eder, gerisini listener'lar halleder. |
| **Open/Closed Principle** | Yeni bir yan etki eklemek için mevcut service koduna dokunmaya gerek yok — sadece yeni bir Listener yazıp `AppServiceProvider`'a eklemek yeterli. |
| **Real-time Broadcasting** | 10 event `ShouldBroadcast` implement ediyor. Laravel Echo + Reverb ile Livewire component'lere canlı push yapılıyor. |
| **Bildirim Katmanı** | 4 farklı listener (`SendStatusChangeNotification`, `SendTaskAssignedNotification`, `SendMemberAddedNotification`) bildirim action'ını tetikliyor. |
| **Analitik Tutarlılık** | `UpdateBurndownSnapshot` ve `RecalculateEpicCompletion` story değişikliklerinde otomatik çalışıyor. |
| **Otomasyon** | `ReturnUnfinishedStoriesToBacklog` sprint kapatma sonrası otomatik temizlik yapıyor. |

---

## 1.3 Tüm Event Sınıfları — Detaylı Envanter

### 1.3.1 Scrum Domain Events (7 adet)

#### `App\Events\Scrum\StoryCreated`
- **Dosya:** `app/Events/Scrum/StoryCreated.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `UserStory $story`, `User $creator`
- **Broadcast Kanalı:** `PrivateChannel("project.{story.project_id}")`
- **Broadcast Adı:** `story.created`
- **Broadcast Payload:** `story_id`, `story_title`, `created_by`
- **Dispatch Noktası:** `UserStoryService::create()` (satır 40)
- **Dinleyen Listener:** *(Yok — yalnızca broadcast için kullanılıyor)*

#### `App\Events\Scrum\StoryStatusChanged`
- **Dosya:** `app/Events/Scrum/StoryStatusChanged.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `UserStory $story`, `string $oldStatus`, `string $newStatus`, `User $changedBy`
- **Broadcast Kanalı:** `PrivateChannel("project.{story.project_id}")`
- **Broadcast Adı:** `story.status-changed`
- **Broadcast Payload:** `story_id`, `old_status`, `new_status`, `changed_by`
- **Dispatch Noktası:** `UserStoryService::changeStatus()` (satır 71)
- **Dinleyen Listener'lar:**
  1. `RecalculateEpicCompletion` — Epic tamamlanma oranını yeniden hesaplar
  2. `SendStatusChangeNotification` — Story sahibine bildirim gönderir
  3. `UpdateBurndownSnapshot` — Sprint burndown verisini günceller

#### `App\Events\Scrum\TaskStatusChanged`
- **Dosya:** `app/Events/Scrum/TaskStatusChanged.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Task $task`, `string $oldStatus`, `string $newStatus`, `User $changedBy`
- **Broadcast Kanalı:** `PrivateChannel("project.{task.userStory.project_id}")`
- **Broadcast Adı:** `task.status-changed`
- **Broadcast Payload:** `task_id`, `story_id`, `old_status`, `new_status`, `changed_by`
- **Dispatch Noktası:** `TaskService::changeStatus()` (satır 56)
- **Dinleyen Listener:** `SendStatusChangeNotification` — Task assignee'sine bildirim gönderir

#### `App\Events\Scrum\TaskAssigned`
- **Dosya:** `app/Events/Scrum/TaskAssigned.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Task $task`, `User $assignee`, `User $assignedBy`
- **Broadcast Kanalı:** `PrivateChannel("user.{assignee.id}")`
- **Broadcast Adı:** `task.assigned`
- **Broadcast Payload:** `task_id`, `task_title`, `assignee_id`, `assigned_by`
- **Dispatch Noktası:** `TaskService::assign()` (satır 72)
- **Dinleyen Listener:** `SendTaskAssignedNotification` — Atanan kişiye bildirim gönderir

#### `App\Events\Scrum\SprintStarted`
- **Dosya:** `app/Events/Scrum/SprintStarted.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Sprint $sprint`, `User $startedBy`
- **Broadcast Kanalı:** `PrivateChannel("project.{sprint.project_id}")`
- **Broadcast Adı:** `sprint.started`
- **Broadcast Payload:** `sprint_id`, `sprint_name`, `started_by`
- **Dispatch Noktası:** `SprintService::start()` (satır 58)
- **Dinleyen Listener:** *(Yok — yalnızca broadcast için kullanılıyor)*

#### `App\Events\Scrum\SprintClosed`
- **Dosya:** `app/Events/Scrum/SprintClosed.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Sprint $sprint`, `User $closedBy`
- **Broadcast Kanalı:** `PrivateChannel("project.{sprint.project_id}")`
- **Broadcast Adı:** `sprint.closed`
- **Broadcast Payload:** `sprint_id`, `sprint_name`, `closed_by`
- **Dispatch Noktası:** `SprintService::close()` (satır 76)
- **Dinleyen Listener:** `ReturnUnfinishedStoriesToBacklog` — Tamamlanmamış story'lerin `sprint_id`'sini null yapar

#### `App\Events\Scrum\SprintScopeChanged`
- **Dosya:** `app/Events/Scrum/SprintScopeChanged.php`
- **Implements:** *(ShouldBroadcast implement ETMİYOR — yalnızca domain event)*
- **Constructor Parametreleri:** `Sprint $sprint`, `UserStory $story`, `string $changeType` (added/removed), `User $changedBy`
- **Dispatch Noktası:** `DetectScopeChangeAction::execute()` (satır 28)
- **Dinleyen Listener:** *(Yok — broadcast olmadığı halde Livewire tarafında `sprint.scope-changed` olarak dinleniyor, ancak AppServiceProvider'da bir listener kaydı mevcut değil)*

### 1.3.2 Issue Domain Events (2 adet)

#### `App\Events\Issue\IssueCreated`
- **Dosya:** `app/Events/Issue/IssueCreated.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Issue $issue`, `User $creator`
- **Broadcast Kanalı:** `PrivateChannel("project.{issue.project_id}")`
- **Broadcast Adı:** `issue.created`
- **Broadcast Payload:** `issue_id`, `title`, `created_by`
- **Dispatch Noktası:** `IssueService::create()` (satır 33)
- **Dinleyen Listener:** *(Yok — yalnızca broadcast için kullanılıyor)*

#### `App\Events\Issue\IssueStatusChanged`
- **Dosya:** `app/Events/Issue/IssueStatusChanged.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Issue $issue`, `string $oldStatus`, `string $newStatus`, `User $changedBy`
- **Broadcast Kanalı:** `PrivateChannel("project.{issue.project_id}")`
- **Broadcast Adı:** `issue.status-changed`
- **Broadcast Payload:** `issue_id`, `old_status`, `new_status`, `changed_by`
- **Dispatch Noktası:** `IssueService::changeStatus()` (satır 62)
- **Dinleyen Listener:** *(AppServiceProvider'da kayıtlı değil, ancak `SendStatusChangeNotification`'ın `handle()` metodu `IssueStatusChanged` instance'ını tanıyıp işleyebiliyor — **bağlantı eksik**)*

### 1.3.3 Project Domain Events (3 adet)

#### `App\Events\Project\ProjectCreated`
- **Dosya:** `app/Events/Project/ProjectCreated.php`
- **Implements:** *(ShouldBroadcast implement ETMİYOR — yalnızca domain event)*
- **Constructor Parametreleri:** `Project $project`, `User $creator`
- **Dispatch Noktası:** `ProjectService::create()` (satır 30)
- **Dinleyen Listener:** *(Yok — hiçbir yerde dinlenmiyor)*

#### `App\Events\Project\MemberAdded`
- **Dosya:** `app/Events/Project/MemberAdded.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Project $project`, `User $member`, `ProjectMembership $membership`, `User $addedBy`
- **Broadcast Kanalları:** `PrivateChannel("user.{member.id}")` + `PrivateChannel("project.{project.id}")`
- **Broadcast Adı:** `member.added`
- **Broadcast Payload:** `project_id`, `project_name`, `member_id`, `role`, `added_by`
- **Dispatch Noktası:** `MembershipService::addMember()` (satır 35)
- **Dinleyen Listener:** `SendMemberAddedNotification` — Eklenen üyeye bildirim gönderir

#### `App\Events\Project\MemberRemoved`
- **Dosya:** `app/Events/Project/MemberRemoved.php`
- **Implements:** *(ShouldBroadcast implement ETMİYOR — yalnızca domain event)*
- **Constructor Parametreleri:** `Project $project`, `User $member`, `User $removedBy`
- **Dispatch Noktası:** `MembershipService::removeMember()` (satır 48)
- **Dinleyen Listener:** *(Yok — hiçbir yerde dinlenmiyor)*

### 1.3.4 Notification Events (1 adet)

#### `App\Events\Notification\NotificationSent`
- **Dosya:** `app/Events/Notification/NotificationSent.php`
- **Implements:** `ShouldBroadcast`
- **Constructor Parametreleri:** `Notification $notification`, `string $userId`
- **Broadcast Kanalı:** `PrivateChannel("user.{userId}")`
- **Broadcast Adı:** `notification.received`
- **Broadcast Payload:** `id`, `type`, `data`
- **Dispatch Noktası:** `SendNotificationAction::execute()` (satır 23)
- **Dinleyen Listener:** *(Yok — Livewire NotificationBell component'i bu broadcast'i dinliyor)*

---

## 1.4 Tüm Listener Sınıfları — Detaylı Envanter

### 1.4.1 `RecalculateEpicCompletion`
- **Dosya:** `app/Listeners/RecalculateEpicCompletion.php`
- **Dinlediği Event:** `StoryStatusChanged`
- **Bağımlılık:** `CalculateEpicCompletionAction` (constructor injection)
- **Mantık:** Story bir epic'e bağlıysa (`epic_id !== null`), epic tamamlanma oranını yeniden hesaplar.
- **Guard:** `$story->epic_id === null` ise early return.

### 1.4.2 `SendStatusChangeNotification`
- **Dosya:** `app/Listeners/SendStatusChangeNotification.php`
- **Dinlediği Event'ler:** `StoryStatusChanged`, `TaskStatusChanged` *(AppServiceProvider'da kayıtlı)*
- **Bağımlılık:** `SendNotificationAction` (constructor injection)
- **Mantık:**
  - `StoryStatusChanged` → Story oluşturucusu, değiştiren kişi değilse bildirim gönderir
  - `TaskStatusChanged` → Task assignee'si, değiştiren kişi değilse bildirim gönderir
  - `IssueStatusChanged` → Issue oluşturucusu, değiştiren kişi değilse bildirim gönderir (**⚠️ handle edebiliyor ama AppServiceProvider'da wiring mevcut değil**)
- **Guard:** Değişikliği yapan kişi aynı zamanda alıcıysa bildirim gönderilmez (self-notification prevention).

### 1.4.3 `UpdateBurndownSnapshot`
- **Dosya:** `app/Listeners/UpdateBurndownSnapshot.php`
- **Dinlediği Event:** `StoryStatusChanged`
- **Bağımlılık:** `SnapshotDailyBurndownAction` (constructor injection)
- **Mantık:** Story bir sprint'e bağlıysa (`sprint_id !== null`), burndown günlük snapshot'ını günceller.
- **Guard:** `$story->sprint_id === null` ise early return.

### 1.4.4 `SendTaskAssignedNotification`
- **Dosya:** `app/Listeners/SendTaskAssignedNotification.php`
- **Dinlediği Event:** `TaskAssigned`
- **Bağımlılık:** `SendNotificationAction` (constructor injection)
- **Mantık:** Atanan kişiye `task_assigned` tipinde bildirim gönderir.

### 1.4.5 `SendMemberAddedNotification`
- **Dosya:** `app/Listeners/SendMemberAddedNotification.php`
- **Dinlediği Event:** `MemberAdded`
- **Bağımlılık:** `SendNotificationAction` (constructor injection)
- **Mantık:** Projeye eklenen üyeye `member_added` tipinde bildirim gönderir.

### 1.4.6 `ReturnUnfinishedStoriesToBacklog`
- **Dosya:** `app/Listeners/ReturnUnfinishedStoriesToBacklog.php`
- **Dinlediği Event:** `SprintClosed`
- **Bağımlılık:** Yok (doğrudan Eloquent)
- **Mantık:** Sprint kapatıldığında `status != Done` olan tüm story'lerin `sprint_id`'sini `null` yapar.

### 1.4.7 `BroadcastProjectUpdate` ⚠️ BOŞ
- **Dosya:** `app/Listeners/BroadcastProjectUpdate.php`
- **Dinlediği Event:** *(AppServiceProvider'da kayıtlı değil)*
- **Mantık:** Sadece yorum var, implementasyon boş. `handle()` metodu hiçbir şey yapmıyor.
- **Durum:** **KULLANILMIYOR — Silinebilir veya implement edilebilir.**

### 1.4.8 `LogActivity` ⚠️ BOŞ
- **Dosya:** `app/Listeners/LogActivity.php`
- **Dinlediği Event:** *(AppServiceProvider'da kayıtlı değil)*
- **Mantık:** Sadece yorum var, implementasyon boş. `handle()` metodu hiçbir şey yapmıyor.
- **Durum:** **KULLANILMIYOR — Silinebilir veya implement edilebilir.**

---

## 1.5 Event Kayıt Haritası (Wiring)

`app/Providers/AppServiceProvider.php` içindeki kayıtlar:

```
StoryStatusChanged ──┬──► RecalculateEpicCompletion
                     ├──► SendStatusChangeNotification
                     └──► UpdateBurndownSnapshot

TaskStatusChanged  ──────► SendStatusChangeNotification

TaskAssigned       ──────► SendTaskAssignedNotification

MemberAdded        ──────► SendMemberAddedNotification

SprintClosed       ──────► ReturnUnfinishedStoriesToBacklog
```

### ⚠️ Kayıt Dışı Kalan (Unwired) Event'ler:

| Event | ShouldBroadcast? | Listener Kaydı | Durum |
|-------|:-:|:-:|--------|
| `StoryCreated` | ✅ | ❌ | Sadece broadcast ile Livewire'a gidiyor |
| `SprintStarted` | ✅ | ❌ | Sadece broadcast ile Livewire'a gidiyor |
| `SprintScopeChanged` | ❌ | ❌ | **Hiçbir yere bağlı değil — broadcast yok, listener yok** |
| `IssueCreated` | ✅ | ❌ | Sadece broadcast ile Livewire'a gidiyor |
| `IssueStatusChanged` | ✅ | ❌ | Broadcast var ama `SendStatusChangeNotification` kaydı **eksik** |
| `ProjectCreated` | ❌ | ❌ | **Hiçbir yere bağlı değil** |
| `MemberRemoved` | ❌ | ❌ | **Hiçbir yere bağlı değil** |
| `NotificationSent` | ✅ | ❌ | Sadece broadcast ile NotificationBell'e gidiyor |

---

## 1.6 Event Dispatch Noktaları — Hangi Service Nereden Dispatch Ediyor?

| Service / Action | Metot | Dispatch Edilen Event |
|-----------------|-------|----------------------|
| `UserStoryService` | `create()` | `StoryCreated::dispatch($story, $user)` |
| `UserStoryService` | `changeStatus()` | `StoryStatusChanged::dispatch($story, $oldStatus, $newStatus, $user)` |
| `TaskService` | `changeStatus()` | `TaskStatusChanged::dispatch($task, $oldStatus, $newStatus, $user)` |
| `TaskService` | `assign()` | `TaskAssigned::dispatch($task, $assignee, $assignedBy)` |
| `SprintService` | `start()` | `SprintStarted::dispatch($sprint, $user)` |
| `SprintService` | `close()` | `SprintClosed::dispatch($sprint, $user)` |
| `ProjectService` | `create()` | `ProjectCreated::dispatch($project, $user)` |
| `MembershipService` | `addMember()` | `MemberAdded::dispatch($project, $user, $membership, $addedBy)` |
| `MembershipService` | `removeMember()` | `MemberRemoved::dispatch($project, $user, $removedBy)` |
| `IssueService` | `create()` | `IssueCreated::dispatch($issue, $user)` |
| `IssueService` | `changeStatus()` | `IssueStatusChanged::dispatch($issue, $oldStatus, $newStatus, $user)` |
| `DetectScopeChangeAction` | `execute()` | `SprintScopeChanged::dispatch($sprint, $story, $changeType, $user)` |
| `SendNotificationAction` | `execute()` | `NotificationSent::dispatch($notification, $userId)` |

**Toplam: 13 dispatch noktası, 6 farklı Service + 2 Action'dan.**

---

## 1.7 Broadcast Kanalları

`routes/channels.php` içinde 3 kanal tanımlı:

| Kanal | Format | Yetkilendirme |
|-------|--------|---------------|
| `App.Models.User.{id}` | Kişisel (Laravel default) | `(int) $user->id === (int) $id` |
| `project.{projectId}` | Proje bazlı | `$user->projectMemberships()->where('project_id', $projectId)->exists()` |
| `user.{userId}` | Kullanıcı bazlı (bildirim) | `$user->id === $userId` |

**Kullanılan kanallar:**
- `project.{id}` → 8 event tarafından kullanılıyor (story, task, sprint, issue, member)
- `user.{id}` → 2 event tarafından kullanılıyor (`TaskAssigned`, `NotificationSent`)
- `MemberAdded` her iki kanalı da kullanıyor (hem proje hem kullanıcı)

---

## 1.8 Livewire Tarafındaki Echo Listener'ları

Her Livewire component `getListeners()` metoduyla hangi broadcast event'lerini dinleyeceğini belirtir. Format:

```
"echo-private:project.{id},.event-name" => 'refreshMetot'
```

### Component → Dinlenen Event Haritası

| Component | Dinlenen Broadcast Event'ler | Refresh Metodu |
|-----------|------------------------------|----------------|
| **kanban-board** | `story.status-changed`, `task.status-changed`, `story.created`, `sprint.started`, `sprint.closed` | `refreshBoard()` |
| **backlog** | `story.created`, `sprint.scope-changed` | `refreshBacklog()` |
| **story-detail** | `task.status-changed`, `task.assigned` | `refreshStoryTasks()` |
| **sprint-list** | `sprint.started`, `sprint.closed`, `sprint.scope-changed` | `refreshSprints()` |
| **epic-list** | `story.created`, `story.status-changed` | `refreshEpics()` |
| **issue-list** | `issue.created`, `issue.status-changed` | `refreshIssues()` |
| **project-dashboard** | `story.status-changed`, `task.status-changed`, `sprint.started`, `sprint.closed`, `issue.created`, `issue.status-changed` | `refreshDashboard()` |
| **project-settings** | `member.added` | `refreshMembers()` |
| **analytics-dashboard** | `story.status-changed`, `task.status-changed`, `sprint.started`, `sprint.closed` | `refreshAnalytics()` |
| **notification-bell** | `notification.received` (user kanalı) | `incrementUnreadCount()` |

### ⚠️ Dikkat: `sprint.scope-changed` Problemi

`backlog` ve `sprint-list` component'leri `sprint.scope-changed` event'ini dinliyor, ancak:
- `SprintScopeChanged` event'i **`ShouldBroadcast` implement etmiyor**
- Bu event yalnızca `Dispatchable` ve `SerializesModels` kullanıyor
- **Dolayısıyla bu event asla broadcast edilmez ve Livewire tarafında hiçbir zaman tetiklenmez**
- Bu bir **bug** veya **eksik implementasyon**

---

## 1.9 Event Akış Diyagramları

### Story Durum Değişikliği Akışı (En Karmaşık)
```
Kullanıcı story durumunu değiştirir
        │
        ▼
UserStoryService::changeStatus()
        │
        ├── StoryStatusChanged::dispatch()
        │       │
        │       ├── [Broadcast] → project.{id} kanalı → "story.status-changed"
        │       │       │
        │       │       ├── kanban-board → refreshBoard()
        │       │       ├── epic-list → refreshEpics()
        │       │       ├── project-dashboard → refreshDashboard()
        │       │       └── analytics-dashboard → refreshAnalytics()
        │       │
        │       ├── [Listener] RecalculateEpicCompletion
        │       │       └── CalculateEpicCompletionAction::execute()
        │       │
        │       ├── [Listener] SendStatusChangeNotification
        │       │       └── SendNotificationAction::execute()
        │       │               └── NotificationSent::dispatch()
        │       │                       └── [Broadcast] → user.{id} → "notification.received"
        │       │                               └── notification-bell → incrementUnreadCount()
        │       │
        │       └── [Listener] UpdateBurndownSnapshot
        │               └── SnapshotDailyBurndownAction::execute()
        │
        └── (return)
```

### Sprint Kapatma Akışı
```
Kullanıcı sprint'i kapatır
        │
        ▼
SprintService::close()
        │
        ├── SprintClosed::dispatch()
        │       │
        │       ├── [Broadcast] → project.{id} kanalı → "sprint.closed"
        │       │       ├── kanban-board → refreshBoard()
        │       │       ├── sprint-list → refreshSprints()
        │       │       ├── project-dashboard → refreshDashboard()
        │       │       └── analytics-dashboard → refreshAnalytics()
        │       │
        │       └── [Listener] ReturnUnfinishedStoriesToBacklog
        │               └── status != Done olan story'lerin sprint_id = null
        │
        └── (return)
```

### Bildirim Akışı (Generic)
```
Herhangi bir Listener → SendNotificationAction::execute()
        │
        ├── Notification kayıt oluşturur (DB)
        │
        ├── NotificationSent::dispatch($notification, $userId)
        │       │
        │       └── [Broadcast] → user.{userId} → "notification.received"
        │               └── notification-bell → incrementUnreadCount()
        │
        └── (return)
```

---

## 1.10 Yetim (Orphan) Event & Listener Analizi

### Dispatch Edilen Ama Listener'ı Olmayan Event'ler

| Event | Broadcast? | Sorun |
|-------|:----------:|-------|
| `ProjectCreated` | ❌ | Dispatch ediliyor ama hiçbir listener veya broadcast tarafından kullanılmıyor. **Tamamen yetim.** |
| `MemberRemoved` | ❌ | Dispatch ediliyor ama hiçbir listener veya broadcast tarafından kullanılmıyor. **Tamamen yetim.** |
| `SprintScopeChanged` | ❌ | Dispatch ediliyor, broadcast yok, listener yok. Livewire tarafında dinleniyor ama tetiklenmez. **Bug.** |

### AppServiceProvider'da Kaydı Olmayan Ama Olması Gereken Bağlantılar

| Event | Beklenen Listener | Açıklama |
|-------|-------------------|----------|
| `IssueStatusChanged` | `SendStatusChangeNotification` | Listener `handle()` metodu `IssueStatusChanged` case'ini tanıyor, ama `Event::listen()` kaydı mevcut değil. **Wiring eksik.** |

### Implement Edilmemiş (Boş) Listener'lar

| Listener | Durum |
|----------|-------|
| `BroadcastProjectUpdate` | `handle()` boş, hiçbir event'e bağlı değil |
| `LogActivity` | `handle()` boş, hiçbir event'e bağlı değil |

---

## 1.11 Refactoring Önerileri

### Öncelik 1 — Bug Düzeltmeleri

1. **`IssueStatusChanged` listener wiring eksik:**
   ```php
   // AppServiceProvider::boot() içine ekle:
   Event::listen(IssueStatusChanged::class, SendStatusChangeNotification::class);
   ```

2. **`SprintScopeChanged` broadcast eksik:**
   - Ya `ShouldBroadcast` implement edilmeli ve `broadcastOn()`, `broadcastAs()` vs. eklenmeli
   - Ya da Livewire component'lerden `sprint.scope-changed` listener'ları kaldırılmalı

### Öncelik 2 — Temizlik

3. **`BroadcastProjectUpdate` listener'ını sil** — Boş implementasyon, hiçbir yere bağlı değil
4. **`LogActivity` listener'ını sil** veya implement et — Boş implementasyon, hiçbir yere bağlı değil
5. **`ProjectCreated` ve `MemberRemoved`** event'leri ya bir listener'a bağlanmalı ya da dispatch çağrıları kaldırılmalı (gelecekte kullanılacaksa bırakılabilir ama YAGNI prensibine aykırı)

### Öncelik 3 — Mimari İyileştirmeler

6. **Event–Listener kaydını `AppServiceProvider`'dan `EventServiceProvider`'a taşı** — Laravel konvansiyonuna daha uygun
7. **Laravel 11 Event Discovery** özelliğini aktifleştirmeyi değerlendir — Manuel `Event::listen()` yerine otomatik keşif
8. **`SendStatusChangeNotification`'daki `match(true)`** pattern'ini refactor et — Her event tipi için ayrı listener oluşturmak SRP'ye daha uygun olur (opsiyonel)

---

# BÖLÜM 2 — Livewire 4 Özellikleri & Component Mimarisi

## 2.1 Livewire 4 vs Livewire 3 — Bu Projede Kullanılan Farklar

### 2.1.1 Volt (Single-File Components) — 🆕 Livewire 4

**Livewire 3:** Component sınıfı `app/Livewire/` altında ayrı PHP dosyasında, template `resources/views/livewire/` altında ayrı Blade dosyasındaydı.

**Livewire 4:** `new class extends Component { ... }` sözdizimi ile PHP sınıfı ve Blade template aynı dosyada (Volt). `app/Livewire/` klasörü bu projede **hiç mevcut değil**.

**Bu projede kullanımı:**
```php
// resources/views/livewire/scrum/kanban-board.blade.php
<?php
use Livewire\Component;

new #[Layout('components.layouts.app')] #[Title('Kanban Board — Canopy')] class extends Component {
    public Project $project;
    // ... tüm PHP kodu burada
}
?>

{{-- Blade template burada --}}
<div> ... </div>
```

**Tüm 14 component** Volt (single-file) formatında yazılmıştır.

### 2.1.2 PHP Attribute'ları (PHP 8 Attributes) — 🆕 Livewire 4

Livewire 3'te `$layout`, `$title` gibi property'ler veya `layout()`, `title()` gibi metodlar kullanılıyordu. Livewire 4'te bunlar PHP 8 attribute'ları olarak tanımlanır.

**Bu projede kullanılan attribute'lar:**

| Attribute | Livewire 3 Karşılığı | Kullanım Sayısı | Açıklama |
|-----------|----------------------|:---------------:|----------|
| `#[Layout('...')]` | `protected $layout` / `->layout()` | 13 | Blade layout belirleme |
| `#[Title('...')]` | `protected $title` / `->title()` | 13 | Sayfa başlığı |
| `#[Computed]` | `getXxxProperty()` magic method | 30+ | Hesaplanmış (computed) property tanımlama |
| `#[Url]` | `protected $queryString` | 3 | URL query string binding (issue-list filtreler) |

### 2.1.3 `#[Computed]` Attribute — 🆕 Livewire 4

**Livewire 3:** Computed property'ler `getXxxProperty()` convention'ı ile tanımlanırdı.

**Livewire 4:** `#[Computed]` PHP attribute'u kullanılır, metot adı doğrudan property adı olur.

```php
// Livewire 3 stili (ESKİ):
public function getStoriesProperty() { ... }

// Livewire 4 stili (BU PROJEDE):
#[Computed]
public function stories(): mixed { ... }
```

**Bu projede 30+ computed property** bu yeni formatta tanımlanmıştır. Cache invalidation `unset($this->propName)` ile yapılır.

### 2.1.4 `Route::livewire()` — 🆕 Livewire 4

**Livewire 3:** Standart Laravel route tanımı + component render.

**Livewire 4:** `Route::livewire()` helper'ı ile doğrudan route tanımı.

```php
// Livewire 3 stili (ESKİ):
Route::get('/board', KanbanBoard::class)->name('projects.board');

// Livewire 4 stili (BU PROJEDE):
Route::livewire('/board', 'scrum.kanban-board')->name('projects.board');
```

Projede **tüm 12 route** bu formatta tanımlanmıştır.

### 2.1.5 `wire:navigate` — 🆕 Livewire 4

**Livewire 3:** Bu özellik yoktu. Sayfa geçişleri tam sayfa yeniden yükleme ile yapılırdı.

**Livewire 4:** `wire:navigate` directive'i SPA benzeri sayfa geçişleri sağlar — tam sayfa yeniden yükleme olmadan içerik güncellenir.

```html
<a href="/projects/{{ $project->slug }}/board" wire:navigate>Board'a Git</a>
```

**Bu projede 20+ yerde** `wire:navigate` kullanılmaktadır: breadcrumbs, sidebar navigasyonu, liste linkleri vb.

### 2.1.6 `wire:model.live` — 🔄 Livewire 4'te Değişti

**Livewire 3:** `wire:model` varsayılan olarak her tuş vuruşunda güncelleme yapardı (live). Debounce için `wire:model.debounce.300ms` kullanılırdı.

**Livewire 4:** `wire:model` varsayılan olarak **deferred** (form submit'te güncelleme). Anlık güncelleme için `wire:model.live` açıkça belirtilmeli.

```html
<!-- Issue filtreler — anlık güncelleme -->
<flux:select wire:model.live="statusFilter" size="sm">
```

**Bu projede 6 yerde** `wire:model.live` kullanılmaktadır (filtre select'leri ve sprint seçici).

### 2.1.7 `wire:loading` ve `wire:loading.attr` — ✅ Livewire 3'te de Vardı (Syntax Aynı)

```html
<flux:button type="submit" variant="primary" wire:loading.attr="disabled">
    <span wire:loading.remove wire:target="create">Oluştur</span>
    <span wire:loading wire:target="create">Oluşturuluyor...</span>
</flux:button>
```

3 component'te kullanılıyor: `create-project`, `login`, `register`.

### 2.1.8 `getListeners()` ile Echo Entegrasyonu — ✅ Livewire 3'te de Vardı (Format Aynı)

```php
public function getListeners(): array
{
    return [
        "echo-private:project.{$this->project->id},.story.status-changed" => 'refreshBoard',
    ];
}
```

Bu format Livewire 3'te de mevcuttu. **10 component** bu yapıyı kullanmaktadır.

> **Not:** Livewire 4'te `#[On('event-name')]` attribute'u da kullanılabilir, ancak **Echo listener'ları için `getListeners()` hâlâ gerekli**dir çünkü `#[On()]` dinamik kanal adlarını desteklemez.

### 2.1.9 Özet Karşılaştırma Tablosu

| Özellik | Livewire 3 | Livewire 4 | Bu Projede Kullanılıyor mu? |
|---------|:----------:|:----------:|:---------------------------:|
| Volt (Single-File Components) | ❌ | ✅ | ✅ (14 component) |
| PHP 8 Attributes (`#[Layout]`, `#[Title]`) | ❌ | ✅ | ✅ (13 component) |
| `#[Computed]` attribute | ❌ | ✅ | ✅ (30+ kullanım) |
| `Route::livewire()` helper | ❌ | ✅ | ✅ (12 route) |
| `wire:navigate` (SPA-like) | ❌ | ✅ | ✅ (20+ link) |
| `wire:model.live` (explicit live) | ❌ (`wire:model` default live idi) | ✅ | ✅ (6 kullanım) |
| `#[Url]` attribute | ❌ (`$queryString` kullanılırdı) | ✅ | ✅ (3 kullanım, issue-list) |
| `#[On('event')]` attribute | ❌ | ✅ | ❌ (kullanılmıyor, `getListeners()` tercih edilmiş) |
| `#[Locked]` attribute | ❌ | ✅ | ❌ |
| `#[Lazy]` attribute | ❌ | ✅ | ❌ |
| `#[Isolate]` attribute | ❌ | ✅ | ❌ |
| `#[Modelable]` attribute | ❌ | ✅ | ❌ |
| `#[Reactive]` attribute | ❌ | ✅ | ❌ |
| `#[Session]` attribute | ❌ | ✅ | ❌ |
| `$this->authorize()` inline auth | ✅ | ✅ | ✅ (story-detail) |
| `WithPagination` trait | ✅ | ✅ | ✅ (issue-list) |
| Flux UI component library | ❌ | ✅ (livewire/flux) | ✅ (Tüm UI) |

---

## 2.2 Volt (Single-File Components) Mimarisi

### Dosya Yapısı
```
resources/views/livewire/
├── analytics/
│   └── analytics-dashboard.blade.php    ← Volt component
├── auth/
│   ├── login.blade.php                  ← Volt component
│   └── register.blade.php              ← Volt component
├── dashboard.blade.php                  ← Volt component
├── issues/
│   └── issue-list.blade.php             ← Volt component
├── notification/
│   └── notification-bell.blade.php      ← Volt component (partial, no Layout)
├── projects/
│   ├── create-project.blade.php         ← Volt component
│   ├── project-dashboard.blade.php      ← Volt component
│   └── project-settings.blade.php       ← Volt component
└── scrum/
    ├── backlog.blade.php                ← Volt component
    ├── epic-list.blade.php              ← Volt component
    ├── kanban-board.blade.php           ← Volt component
    ├── sprint-list.blade.php            ← Volt component
    └── story-detail.blade.php           ← Volt component
```

### Component Anatomisi (Genel Şablon)

```php
<?php
// 1. Importlar
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// 2. Inline sınıf tanımı + attribute'lar
new #[Layout('components.layouts.app')] #[Title('Sayfa Başlığı')] class extends Component {

    // 3. Public property'ler (state)
    public Project $project;

    // 4. mount() — ilk yükleme
    public function mount(Project $project): void { ... }

    // 5. getListeners() — Echo entegrasyonu (opsiyonel)
    public function getListeners(): array { ... }

    // 6. refresh metodu — computed cache invalidation
    public function refreshXxx(): void { unset($this->prop1, $this->prop2); }

    // 7. Action metodları — kullanıcı etkileşimleri
    public function createXxx(): void { ... }

    // 8. #[Computed] property'ler — türetilmiş veri
    #[Computed]
    public function items(): mixed { ... }
}
?>

{{-- 9. Blade template --}}
<div>
    {{-- Flux UI component'leri ile arayüz --}}
</div>
```

---

## 2.3 Component Envanteri — 14 Bileşenin Detaylı Dökümü

### 2.3.1 `auth/login`
- **Route:** `/login`
- **Layout:** `components.layouts.guest`
- **State:** `email`, `password`, `remember`
- **Actions:** `login()`
- **Service Bağımlılığı:** `AuthService`
- **Echo Listener:** Yok
- **Computed Properties:** Yok

### 2.3.2 `auth/register`
- **Route:** `/register`
- **Layout:** `components.layouts.guest`
- **State:** `name`, `email`, `password`, `password_confirmation`
- **Actions:** `register()`
- **Service Bağımlılığı:** `AuthService`
- **Echo Listener:** Yok
- **Computed Properties:** Yok

### 2.3.3 `dashboard`
- **Route:** `/dashboard`
- **Layout:** `components.layouts.app`
- **State:** Yok (sadece computed)
- **Actions:** Yok
- **Echo Listener:** Yok
- **Computed Properties:** `projects()` — kullanıcının projeleri

### 2.3.4 `projects/create-project`
- **Route:** `/projects/create`
- **Layout:** `components.layouts.app`
- **State:** `name`, `description`
- **Actions:** `create()`
- **Service Bağımlılığı:** `ProjectService`
- **Echo Listener:** Yok
- **Computed Properties:** Yok
- **Özel:** `wire:loading` kullanıyor

### 2.3.5 `projects/project-dashboard`
- **Route:** `/projects/{project:slug}`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`
- **Actions:** Yok (salt okunur dashboard)
- **Echo Listener:** 6 event (story.status-changed, task.status-changed, sprint.started, sprint.closed, issue.created, issue.status-changed)
- **Computed Properties:** `activeSprint()`, `backlogCount()`, `totalStories()`, `doneStories()`, `openIssues()`, `recentStories()`

### 2.3.6 `projects/project-settings`
- **Route:** `/projects/{project:slug}/settings`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, `projectName`, `projectDescription`, `newMemberEmail`, `newMemberRole`, `showAddMember`
- **Actions:** `saveProject()`, `addMember()`, `removeMember()`, `changeRole()`, `deleteProject()`
- **Service Bağımlılıkları:** `ProjectService`, `MembershipService`
- **Echo Listener:** 1 event (member.added)
- **Computed Properties:** `members()`

### 2.3.7 `scrum/backlog`
- **Route:** `/projects/{project:slug}/backlog`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, `newStoryTitle`, `filterEpicId`, `showCreateForm`
- **Actions:** `createStory()`, `moveToSprint()`, `reorder()`
- **Service Bağımlılıkları:** `UserStoryService`, `SprintService`
- **Echo Listener:** 2 event (story.created, sprint.scope-changed)
- **Computed Properties:** `stories()`, `sprints()`, `epics()`
- **Özel:** Drag-and-drop sıralama (`$wire.reorder(ids)`)

### 2.3.8 `scrum/kanban-board`
- **Route:** `/projects/{project:slug}/board`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, `selectedSprintId`
- **Actions:** `changeTaskStatus()`, `changeStoryStatus()`
- **Service Bağımlılıkları:** `TaskService`, `UserStoryService`
- **Echo Listener:** 5 event (story.status-changed, task.status-changed, story.created, sprint.started, sprint.closed)
- **Computed Properties:** `sprint()`, `columns()`, `sprints()`
- **Özel:** Drag-and-drop sütunlar arası (`$wire.changeStoryStatus(dragging, status)`)

### 2.3.9 `scrum/sprint-list`
- **Route:** `/projects/{project:slug}/sprints`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, form alanları (create + edit)
- **Actions:** `createSprint()`, `startSprint()`, `closeSprint()`, `editSprint()`, `updateSprint()`, `deleteSprint()`
- **Service Bağımlılığı:** `SprintService`
- **Echo Listener:** 3 event (sprint.started, sprint.closed, sprint.scope-changed)
- **Computed Properties:** `sprints()`

### 2.3.10 `scrum/epic-list`
- **Route:** `/projects/{project:slug}/epics`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, form alanları (create + edit)
- **Actions:** `createEpic()`, `editEpic()`, `updateEpic()`, `deleteEpic()`
- **Service Bağımlılığı:** `EpicService`
- **Echo Listener:** 2 event (story.created, story.status-changed)
- **Computed Properties:** `epics()`

### 2.3.11 `scrum/story-detail`
- **Route:** `/projects/{project:slug}/stories/{story}`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, `UserStory $story`, task form, edit alanları, `estimationPoints`
- **Actions:** `saveTitle()`, `saveDescription()`, `updateEpic()`, `addTask()`, `toggleTaskStatus()`, `assignTask()`, `deleteTask()`, `saveEstimation()`, `transitionStatus()`, `uploadAttachment()`, `deleteAttachment()`
- **Service Bağımlılıkları:** `UserStoryService`, `TaskService`
- **Echo Listener:** 2 event (task.status-changed, task.assigned)
- **Computed Properties:** `epics()`, `members()`
- **Özel:** `$this->authorize('estimate', $this->story)` — policy-based yetkilendirme

### 2.3.12 `issues/issue-list`
- **Route:** `/projects/{project:slug}/issues`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, 3 URL filtresi (`#[Url]`), create/edit form alanları
- **Actions:** `createIssue()`, `editIssue()`, `updateIssue()`, `deleteIssue()`, `changeStatus()`, `assignIssue()`
- **Service Bağımlılığı:** `IssueService`
- **Echo Listener:** 2 event (issue.created, issue.status-changed)
- **Computed Properties:** `issues()`, `counts()`
- **Traits:** `WithPagination`
- **Özel:** `#[Url]` attribute ile filtre state'i URL'de tutulur

### 2.3.13 `analytics/analytics-dashboard`
- **Route:** `/projects/{project:slug}/analytics`
- **Layout:** `components.layouts.app`
- **State:** `Project $project`, `velocitySprintCount`
- **Actions:** Yok (salt okunur)
- **Service Bağımlılıkları:** `VelocityService`, `BurndownService`
- **Echo Listener:** 4 event (story.status-changed, task.status-changed, sprint.started, sprint.closed)
- **Computed Properties:** `activeSprint()`, `velocityData()`, `burndownData()`, `stats()`, `totalStories()`, `completedStories()`, `totalIssues()`, `openIssues()`, `closedSprints()`, `completionRate()`

### 2.3.14 `notification/notification-bell`
- **Route:** Yok (partial component — layout içine gömülü)
- **Layout:** Yok (standalone partial)
- **State:** `unreadCount`, `showPanel`, `userId`
- **Actions:** `togglePanel()`, `markAsRead()`, `markAllAsRead()`
- **Echo Listener:** 1 event (notification.received, user kanalı)
- **Computed Properties:** `notifications()`
- **Özel:** `@entangle('showPanel')` ile Alpine.js state senkronizasyonu, `x-on:click.outside`

---

## 2.4 Routing Yapısı (`Route::livewire`)

```php
// routes/web.php

// Guest (misafir) rotaları
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
});

// Authenticated (kimlik doğrulanmış) rotaları
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/projects/create', 'projects.create-project')->name('projects.create');

    // Proje kapsamlı rotalar
    Route::prefix('/projects/{project:slug}')->group(function () {
        Route::livewire('/', 'projects.project-dashboard')->name('projects.show');
        Route::livewire('/backlog', 'scrum.backlog')->name('projects.backlog');
        Route::livewire('/board', 'scrum.kanban-board')->name('projects.board');
        Route::livewire('/sprints', 'scrum.sprint-list')->name('projects.sprints');
        Route::livewire('/epics', 'scrum.epic-list')->name('projects.epics');
        Route::livewire('/stories/{story}', 'scrum.story-detail')->name('projects.stories.show');
        Route::livewire('/issues', 'issues.issue-list')->name('projects.issues');
        Route::livewire('/analytics', 'analytics.analytics-dashboard')->name('projects.analytics');
        Route::livewire('/settings', 'projects.project-settings')->name('projects.settings');
    });
});
```

**Toplam:** 12 Livewire route + 1 redirect + 1 POST (logout)

---

## 2.5 Ortak Kullanılan Livewire 4 Pattern'leri

### Pattern 1: Computed Property + Cache Invalidation
```php
#[Computed]
public function stories(): mixed
{
    return $this->project->userStories()->with(['epic', 'creator'])->get();
}

// Real-time event geldiğinde cache'i temizle:
public function refreshBacklog(): void
{
    unset($this->stories, $this->sprints);
}
```
**Kullanılan Component'ler:** 12/14 (login, register hariç)

### Pattern 2: Alpine.js + `$wire` Bridge
```javascript
// Blade template içinde JS ↔ Livewire iletişimi
$wire.reorder(ids);          // backlog drag-drop
$wire.changeStoryStatus(id, status); // kanban drag-drop
$wire.set('showPanel', false);       // notification panel
```
**Kullanılan Component'ler:** backlog, kanban-board, notification-bell

### Pattern 3: Service Layer Delegation
```php
public function createStory(): void
{
    $this->validate([...]);
    app(UserStoryService::class)->create($data, $this->project, auth()->user());
}
```
**Tüm CRUD operasyonları** ilgili Service sınıfına delegate edilir. Livewire component'lerde doğrudan Eloquent işlemi yapılmaz (Computed property query'leri hariç).

### Pattern 4: Echo Real-time Refresh
```php
public function getListeners(): array
{
    return [
        "echo-private:project.{$this->project->id},.event-name" => 'refreshMethod',
    ];
}
```
**10/14 component** bu pattern'i kullanır.

---

## 2.6 Real-time Entegrasyon Mimarisi

### Teknoloji Stack'i
```
Laravel Reverb (WebSocket Server)
        │
        ├── Laravel Echo (JS Client) — resources/js/echo.js
        │       └── Pusher.js protocol
        │
        ├── Private Channels — routes/channels.php
        │       ├── project.{projectId} → proje üyelik kontrolü
        │       └── user.{userId} → kullanıcı kimlik kontrolü
        │
        └── Livewire Echo Listener — getListeners() formatı
                └── "echo-private:channel,.event" => 'handler'
```

### Akış
```
1. Service::method()
   └── Event::dispatch()

2. Laravel Event System
   ├── Listener'lar çalışır (DB, analiz, bildirim)
   └── ShouldBroadcast → Reverb WebSocket Server'a gönderilir

3. Reverb → Laravel Echo (client-side JS)
   └── Livewire.on('echo-private:channel,.event')

4. Livewire component'in handler metodu tetiklenir
   └── unset($this->computedProp) → Computed property cache temizlenir
   └── Blade template yeniden render edilir
```

---

## 2.7 Flux UI Entegrasyonu

Proje `livewire/flux` (^2.13) paketini kullanmaktadır. Flux, Livewire 4 için tasarlanmış bir UI component kütüphanesidir.

### Kullanılan Flux Component'leri

| Component | Kullanım Alanı |
|-----------|----------------|
| `<flux:heading>` | Sayfa ve bölüm başlıkları |
| `<flux:text>` | Metin paragrafları |
| `<flux:button>` | Butonlar (variant: primary, ghost, outline, danger) |
| `<flux:input>` | Text input alanları |
| `<flux:textarea>` | Çok satırlı metin alanları |
| `<flux:select>` | Dropdown seçiciler |
| `<flux:badge>` | Durum etiketleri (color: sky, amber, emerald, red) |
| `<flux:icon>` | İkon gösterimi (Heroicons) |
| `<flux:card>` | Kart container'ları |
| `<flux:modal>` | Modal dialog'lar |
| `<flux:breadcrumbs>` | Sayfa yol haritası |
| `<flux:navlist>` | Sidebar navigasyon listesi |
| `<flux:sidebar>` | Yan menü (kenar çubuğu) |
| `<flux:link>` | Stil uygulanmış linkler |
| `<flux:separator>` | Ayırıcı çizgiler |

---

## 2.8 Livewire Component Mimarisi Özet Tablosu

| # | Component | Route | Echo Events | Computed Props | Actions | Service |
|:-:|-----------|-------|:-----------:|:--------------:|:-------:|---------|
| 1 | auth/login | `/login` | 0 | 0 | 1 | AuthService |
| 2 | auth/register | `/register` | 0 | 0 | 1 | AuthService |
| 3 | dashboard | `/dashboard` | 0 | 1 | 0 | — |
| 4 | projects/create-project | `/projects/create` | 0 | 0 | 1 | ProjectService |
| 5 | projects/project-dashboard | `/projects/{slug}` | 6 | 6 | 0 | — |
| 6 | projects/project-settings | `/projects/{slug}/settings` | 1 | 1 | 5 | ProjectService, MembershipService |
| 7 | scrum/backlog | `/projects/{slug}/backlog` | 2 | 3 | 3 | UserStoryService, SprintService |
| 8 | scrum/kanban-board | `/projects/{slug}/board` | 5 | 3 | 2 | TaskService, UserStoryService |
| 9 | scrum/sprint-list | `/projects/{slug}/sprints` | 3 | 1 | 6 | SprintService |
| 10 | scrum/epic-list | `/projects/{slug}/epics` | 2 | 1 | 4 | EpicService |
| 11 | scrum/story-detail | `/projects/{slug}/stories/{story}` | 2 | 2 | 11 | UserStoryService, TaskService |
| 12 | issues/issue-list | `/projects/{slug}/issues` | 2 | 2 | 6 | IssueService |
| 13 | analytics/analytics-dashboard | `/projects/{slug}/analytics` | 4 | 10 | 0 | VelocityService, BurndownService |
| 14 | notification/notification-bell | *(partial)* | 1 | 1 | 3 | — |
| | **TOPLAM** | **12 route** | **28** | **31** | **43** | **8 service** |

---

> **Rapor Sonu**
>
> Bu rapor, Canopy projesindeki tüm event-listener yapısını ve Livewire 4 component mimarisini detaylıca kapsamaktadır.
> Refactoring çalışmasına başlamadan önce **Bölüm 1.10 (Yetim Analizi)** ve **Bölüm 1.11 (Refactoring Önerileri)** bölümlerini öncelikli olarak inceleyin.
