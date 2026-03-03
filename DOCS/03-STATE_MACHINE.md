# 03 — State Machine

User Story, Task ve Issue varlıkları için durum makinesi tanımları, geçiş kuralları ve tetiklenen domain event'ler.

**İlişkili Dokümanlar:** [Domain Model](./02-DOMAIN_MODEL.md) | [Business Rules](./04-BUSINESS_RULES.md) | [Notification System](./11-NOTIFICATION_SYSTEM.md)

---

## 1. Genel Yapı

Sistem 3 durumlu basit bir state machine kullanır. Tüm stateful entity'ler (UserStory, Task, Issue) aynı durum setini paylaşır.

```
         ┌──────────────────────┐
         │                      │
         ▼                      │
    ┌─────────┐           ┌─────┴───────┐
    │   NEW   │──────────►│ IN_PROGRESS │
    └─────────┘           └──────┬──────┘
         ▲                       │
         │                       ▼
         │                ┌──────────────┐
         └────────────────│     DONE     │
            (reopen)      └──────────────┘
```

### Durum Tanımları

| Durum | Enum Value | Açıklama |
|-------|-----------|----------|
| **New** | `new` | Yeni oluşturulmuş, henüz üzerinde çalışılmaya başlanmamış |
| **In Progress** | `in_progress` | Aktif olarak üzerinde çalışılıyor |
| **Done** | `done` | Tamamlanmış |

---

## 2. Geçiş Kuralları

### 2.1 Tüm Entity'ler İçin Ortak Geçişler

| # | Kaynak | Hedef | Koşul | Açıklama |
|---|--------|-------|-------|----------|
| T1 | `new` | `in_progress` | — | Çalışmaya başlama |
| T2 | `in_progress` | `done` | — | Tamamlama |
| T3 | `in_progress` | `new` | — | Geri alma (rollback) |
| T4 | `done` | `in_progress` | — | Yeniden açma (reopen) |

### 2.2 Yasaklanmış Geçişler

| Kaynak | Hedef | Neden |
|--------|-------|-------|
| `new` | `done` | Direkt tamamlama yasak — önce `in_progress` olmalı |
| `done` | `new` | Direkt `new`'e dönemez — önce `in_progress`'e alınmalı |

### 2.3 Entity-Spesifik Ek Koşullar

#### Task

| Geçiş | Ek Koşul |
|--------|----------|
| `new` → `in_progress` | `assigned_to` alanı dolu olmalı. Atanmamış task başlatılamaz. |

#### UserStory

| Geçiş | Ek Koşul |
|--------|----------|
| `in_progress` → `done` | Herhangi bir koşul yok — ama `CheckEpicCompletion` event'i tetiklenir |

#### Issue

| Geçiş | Ek Koşul |
|--------|----------|
| — | Ek koşul yok. Tüm geçişler serbest. |

---

## 3. Geçiş Matrisi (İzin Tablosu)

Satır = mevcut durum, sütun = hedef durum. ✅ = izinli, ❌ = yasak.

| | → new | → in_progress | → done |
|---|:---:|:---:|:---:|
| **new** | — | ✅ | ❌ |
| **in_progress** | ✅ | — | ✅ |
| **done** | ❌ | ✅ | — |

---

## 4. Domain Events

Her durum geçişi bir veya daha fazla domain event fırlatır. Bu event'ler listener'lar tarafından dinlenir ve yan etkileri (side effects) tetikler.

### 4.1 Fırlatılan Event'ler

| Event | Tetiklenme Koşulu | Payload |
|-------|-------------------|---------|
| `StoryStatusChanged` | UserStory durum geçişi | `{story, oldStatus, newStatus, changedBy}` |
| `TaskStatusChanged` | Task durum geçişi | `{task, oldStatus, newStatus, changedBy}` |
| `IssueStatusChanged` | Issue durum geçişi | `{issue, oldStatus, newStatus, changedBy}` |
| `StatusReopened` | Herhangi bir entity `done` → `in_progress` | `{entity, entityType, reopenedBy}` |

### 4.2 Event → Listener Zinciri

#### StoryStatusChanged (→ done)

```
StoryStatusChanged(story, 'in_progress', 'done', user)
    │
    ├──► RecalculateEpicCompletion
    │        Epic tamamlanma yüzdesini yeniden hesapla
    │
    ├──► UpdateBurndownSnapshot
    │        Sprint burndown verilerini güncelle
    │
    ├──► SendStatusChangeNotification
    │        İlgili kullanıcılara in-app bildirim gönder
    │
    └──► LogActivity
             activity_logs tablosuna kayıt oluştur
```

#### StoryStatusChanged (→ in_progress, reopen dahil)

```
StoryStatusChanged(story, 'done', 'in_progress', user)
    │
    ├──► RecalculateEpicCompletion
    │        Epic yüzdesini azalt
    │
    ├──► UpdateBurndownSnapshot
    │        Burndown'ı geri güncelle
    │
    ├──► SendStatusChangeNotification
    │
    └──► LogActivity
```

#### TaskStatusChanged

```
TaskStatusChanged(task, oldStatus, newStatus, user)
    │
    ├──► SendStatusChangeNotification
    │        Task sahibine ve atanmış kullanıcıya bildirim
    │
    └──► LogActivity
```

#### IssueStatusChanged

```
IssueStatusChanged(issue, oldStatus, newStatus, user)
    │
    ├──► SendStatusChangeNotification
    │
    └──► LogActivity
```

---

## 5. Implementasyon Yaklaşımı

### 5.1 HasStateMachine Trait

Tüm stateful model'lerin kullanacağı ortak trait:

```php
// app/Traits/HasStateMachine.php

trait HasStateMachine
{
    /**
     * İzin verilen geçişleri tanımlar.
     * Alt sınıflar override edebilir.
     */
    public static function allowedTransitions(): array
    {
        return [
            'new'         => ['in_progress'],
            'in_progress' => ['new', 'done'],
            'done'        => ['in_progress'],
        ];
    }

    /**
     * Durum geçişi yapılabilir mi kontrol eder.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = static::allowedTransitions()[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }

    /**
     * Durum geçişini gerçekleştirir.
     * Geçiş yasak ise InvalidStatusTransitionException fırlatır.
     */
    public function transitionTo(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException(
                currentStatus: $this->status,
                targetStatus: $newStatus,
                entity: static::class
            );
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        // Domain event fırlatımı Service katmanında yapılır
    }
}
```

### 5.2 Service Katmanında Kullanım

```php
// app/Services/UserStoryService.php

class UserStoryService
{
    public function __construct(
        private ChangeStatusAction $changeStatusAction
    ) {}

    public function changeStatus(UserStory $story, string $newStatus, User $user): UserStory
    {
        return DB::transaction(function () use ($story, $newStatus, $user) {
            $oldStatus = $story->status;

            $this->changeStatusAction->execute($story, $newStatus);

            StoryStatusChanged::dispatch($story, $oldStatus, $newStatus, $user);

            return $story->fresh();
        });
    }
}
```

### 5.3 Task-Spesifik Geçiş Kontrolü

```php
// app/Actions/Scrum/ChangeTaskStatusAction.php

class ChangeTaskStatusAction
{
    public function execute(Task $task, string $newStatus): Task
    {
        // Task'a özel ek koşul: in_progress'e geçiş için atama zorunlu
        if ($newStatus === 'in_progress' && $task->assigned_to === null) {
            throw new TaskNotAssignedException($task);
        }

        $task->transitionTo($newStatus);
        return $task;
    }
}
```

---

## 6. Hata Durumları

| Exception | Tetiklenme | HTTP Status |
|-----------|-----------|-------------|
| `InvalidStatusTransitionException` | Yasaklanmış geçiş denendiğinde | 422 |
| `TaskNotAssignedException` | Atanmamış task başlatılmaya çalışıldığında | 422 |

---

## 7. State Machine Akış Şeması (Tam)

```
                     ┌─────── UserStory ─────────┐
                     │                            │
   ┌─────────┐  T1  │  ┌──────────────┐  T2     │  ┌──────────┐
   │   NEW   │──────►│  │ IN_PROGRESS  │────────►│  │   DONE   │
   └─────────┘       │  └──────┬───────┘         │  └────┬─────┘
        ▲            │         │  T3              │       │ T4
        │            │         ▼                  │       │
        │            │  ┌──────────┐              │       │
        └────────────│──│  (back)  │              │       │
                     │  └──────────┘              │       │
                     └────────────────────────────┘       │
                                                          │
    Events fired:                                         │
    ├── T1: StoryStatusChanged                            │
    ├── T2: StoryStatusChanged + CheckEpicCompletion      │
    ├── T3: StoryStatusChanged                            │
    └── T4: StatusReopened + StoryStatusChanged ◄─────────┘

    All transitions → LogActivity + SendStatusChangeNotification
```

---

**Önceki:** [02-DOMAIN_MODEL.md](./02-DOMAIN_MODEL.md)
**Sonraki:** [04-BUSINESS_RULES.md](./04-BUSINESS_RULES.md)
