# 11 — Notification System

Event-driven bildirim akışı, bildirim türleri, in-app kanal, Laravel Broadcasting / Reverb entegrasyonu.

**İlişkili Dokümanlar:** [State Machine](./03-STATE_MACHINE.md) | [API Design](./07-API_DESIGN.md) | [Infrastructure](./09-INFRASTRUCTURE.md)

---

## 1. Genel Bakış

Bildirim sistemi **Event-Driven** mimaride çalışır. Business event'ler → Listener'lar → Notification gönderimi.

**Kanal:** Sadece **In-App** (veritabanı + WebSocket broadcast). Email/SMS MVP dışında.

```
Action → Service (dispatch Event) → Listener → NotificationService → DB + Broadcast
```

---

## 2. Bildirim Türleri

| Kod | Trigger Event | Alıcı | Mesaj Şablonu |
|-----|--------------|-------|---------------|
| `N-01` | `StoryStatusChanged` | Story owner + proje moderatörleri | "{user} changed '{story}' status to {status}" |
| `N-02` | `TaskStatusChanged` | Task assigned_to + story owner | "{user} changed task '{task}' status to {status}" |
| `N-03` | `TaskAssigned` | Atanan kullanıcı | "{user} assigned task '{task}' to you" |
| `N-04` | `IssueCreated` | Proje moderatör + owner | "{user} created issue '{issue}' in {project}" |
| `N-05` | `IssueStatusChanged` | Issue reporter + assigned | "{user} changed issue '{issue}' status to {status}" |
| `N-06` | `MemberAdded` | Eklenen kullanıcı | "You were added to project '{project}' as {role}" |
| `N-07` | `MemberRemoved` | Çıkarılan kullanıcı | "You were removed from project '{project}'" |
| `N-08` | `SprintStarted` | Tüm proje üyeleri | "Sprint '{sprint}' has started in {project}" |
| `N-09` | `SprintClosed` | Tüm proje üyeleri | "Sprint '{sprint}' has been closed in {project}" |
| `N-10` | `SprintScopeChanged` | Proje owner + moderatörler | "Sprint '{sprint}' scope changed: {change_type} {points} points" |

---

## 3. Notification Model

```php
// app/Models/Notification.php
class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',        // JSON — ilgili entity bilgileri
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // ─── Scopes ───
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ─── Relations ───
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## 4. Service + Action Katmanı

### 4.1 SendNotificationAction

```php
// app/Actions/Notification/SendNotificationAction.php
class SendNotificationAction
{
    /**
     * Tek bir kullanıcıya bildirim gönderir.
     */
    public function execute(
        User $recipient,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): Notification {
        return Notification::create([
            'user_id' => $recipient->id,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }
}
```

### 4.2 MarkAsReadAction

```php
// app/Actions/Notification/MarkAsReadAction.php
class MarkAsReadAction
{
    /**
     * Tek veya toplu okundu işareti.
     */
    public function execute(User $user, ?string $notificationId = null): int
    {
        $query = Notification::forUser($user->id)->unread();

        if ($notificationId) {
            $query->where('id', $notificationId);
        }

        return $query->update(['read_at' => now()]);
    }
}
```

### 4.3 NotificationService

```php
// app/Services/NotificationService.php
class NotificationService
{
    public function __construct(
        private SendNotificationAction $sendAction,
        private MarkAsReadAction $markReadAction,
    ) {}

    /**
     * Birden fazla alıcıya bildirim gönderir + broadcast.
     */
    public function notifyMany(
        Collection $recipients,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $projectId = null,
    ): void {
        DB::transaction(function () use ($recipients, $type, $title, $body, $data, $projectId) {
            foreach ($recipients as $recipient) {
                $notification = $this->sendAction->execute(
                    $recipient, $type, $title, $body, $data
                );

                // WebSocket broadcast
                broadcast(new NotificationSent($notification, $recipient->id));
            }
        });
    }

    /**
     * Tek bir kullanıcıya bildirim gönderir.
     */
    public function notifyOne(
        User $recipient,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): Notification {
        $notification = $this->sendAction->execute(
            $recipient, $type, $title, $body, $data
        );

        broadcast(new NotificationSent($notification, $recipient->id));

        return $notification;
    }

    public function markAsRead(User $user, ?string $notificationId = null): int
    {
        return $this->markReadAction->execute($user, $notificationId);
    }

    public function getUnreadCount(User $user): int
    {
        return Notification::forUser($user->id)->unread()->count();
    }
}
```

---

## 5. Event → Listener → Notification Akışı

### 5.1 Listener Örneği

```php
// app/Listeners/SendStatusChangeNotification.php
class SendStatusChangeNotification
{
    public function __construct(private NotificationService $notificationService) {}

    public function handle(StoryStatusChanged $event): void
    {
        $story   = $event->story;
        $user    = $event->user;
        $project = $story->project;

        // Alıcılar: story owner + proje moderatörleri (tetikleyen kişi hariç)
        $recipients = $project->memberships()
            ->whereIn('role', [ProjectRole::Owner, ProjectRole::Moderator])
            ->with('user')
            ->get()
            ->pluck('user')
            ->push($story->createdBy)
            ->unique('id')
            ->reject(fn ($u) => $u->id === $user->id);

        $this->notificationService->notifyMany(
            recipients: $recipients,
            type: 'story_status_changed',
            title: 'Story Status Changed',
            body: "{$user->name} changed '{$story->title}' status to {$event->newStatus->value}",
            data: [
                'project_id' => $project->id,
                'story_id'   => $story->id,
                'old_status' => $event->oldStatus->value,
                'new_status' => $event->newStatus->value,
            ],
        );
    }
}
```

### 5.2 Event Binding (EventServiceProvider)

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    // Scrum Events
    StoryStatusChanged::class => [
        RecalculateEpicCompletion::class,
        SendStatusChangeNotification::class,
        LogActivity::class,
    ],
    TaskStatusChanged::class => [
        SendStatusChangeNotification::class,
        LogActivity::class,
    ],
    TaskAssigned::class => [
        SendTaskAssignedNotification::class,
        LogActivity::class,
    ],
    SprintStarted::class => [
        SendSprintNotification::class,
        LogActivity::class,
    ],
    SprintClosed::class => [
        ReturnUnfinishedStoriesToBacklog::class,
        SendSprintNotification::class,
        LogActivity::class,
    ],
    SprintScopeChanged::class => [
        UpdateBurndownSnapshot::class,
        SendScopeChangeNotification::class,
        LogActivity::class,
    ],

    // Issue Events
    IssueCreated::class => [
        SendIssueNotification::class,
        LogActivity::class,
    ],
    IssueStatusChanged::class => [
        SendStatusChangeNotification::class,
        LogActivity::class,
    ],

    // Project Events
    MemberAdded::class => [
        SendMemberAddedNotification::class,
        LogActivity::class,
    ],
    MemberRemoved::class => [
        SendMemberRemovedNotification::class,
        LogActivity::class,
    ],
];
```

---

## 6. WebSocket Broadcasting

### 6.1 NotificationSent Event

```php
// app/Events/NotificationSent.php
class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification,
        public string $userId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    public function broadcastWith(): array
    {
        return [
            'id'    => $this->notification->id,
            'type'  => $this->notification->type,
            'title' => $this->notification->title,
            'body'  => $this->notification->body,
            'data'  => $this->notification->data,
            'time'  => $this->notification->created_at->toIso8601String(),
        ];
    }
}
```

### 6.2 Channel Authorization

```php
// routes/channels.php
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
```

### 6.3 Livewire Dinleme

```php
// app/Livewire/Notification/NotificationBell.php
class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function mount()
    {
        $this->unreadCount = app(NotificationService::class)
            ->getUnreadCount(auth()->user());
    }

    #[On('echo-private:user.{userId},notification.received')]
    public function onNotificationReceived()
    {
        $this->unreadCount++;
    }

    public function getUserIdProperty(): string
    {
        return auth()->id();
    }

    public function render()
    {
        return view('livewire.notification.notification-bell');
    }
}
```

---

## 7. Bildirim Yaşam Döngüsü

```
1. Business Event oluşur (ör: StoryStatusChanged)
        │
2. Listener çalışır (SendStatusChangeNotification)
        │
3. NotificationService.notifyMany() çağrılır
        │
4. ┌── SendNotificationAction → DB INSERT (notifications tablosu)
   └── broadcast(NotificationSent) → Redis → Reverb → WebSocket
        │
5. Client (NotificationBell) WebSocket mesajını alır
        │
6. Kullanıcı bildirime tıklar → markAsRead API → MarkAsReadAction
```

---

## 8. Temizlik Stratejisi

30 günden eski okunan bildirimler periyodik olarak silinir:

```php
// routes/console.php (Laravel 11 scheduler)
Schedule::command('model:prune', ['--model' => Notification::class])
    ->weekly();

// app/Models/Notification.php
class Notification extends Model implements MassPrunable
{
    public function prunable(): Builder
    {
        return static::where('read_at', '<', now()->subDays(30));
    }
}
```

---

**Önceki:** [10-ANALYTICS_ENGINE.md](./10-ANALYTICS_ENGINE.md)
**Sonraki:** [12-FILE_MANAGEMENT.md](./12-FILE_MANAGEMENT.md)
