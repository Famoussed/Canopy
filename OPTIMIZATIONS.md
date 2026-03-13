# Canopy — Tam Optimizasyon Denetim Raporu

> **Tarih:** 10 Mart 2026
> **Kapsam:** Tüm uygulama kodu — Models, Actions, Services, Controllers, Livewire, Events, Listeners, Policies, Migrations, Routes, Frontend
> **Toplam İncelenen Dosya:** ~170+

---

## 1) Optimizasyon Özeti

### Mevcut Durum
Canopy, temiz mimari ayrımı (Action → Service → Controller), düzgün state machine pattern'i ve iyi olay yönetimi ile **sağlam bir temel** üzerine kurulmuş. Ancak, **N+1 sorgu problemleri**, **eksik eager loading**, **tekrarlanan policy sorguları** ve **döngü içi veritabanı sorguları** gibi kritik performans sorunları mevcut.

### En Yüksek Etkili 3 İyileştirme

| # | İyileştirme | Tahmini Etki |
|---|-------------|-------------|
| 1 | **CalculateBurndownAction'daki döngü içi sorgular** — Sprint günü başına 1 sorgu → tek sorguya düşürme | **15-30x hızlanma** |
| 2 | **Policy'lerdeki tekrarlanan `getMemberRole()` sorguları** — Request başına 5-10 gereksiz sorgu | **%80 sorgu azaltma** |
| 3 | **Eksik veritabanı indeksleri** (created_by, total_points vb.) — Tam tablo taramaları | **10-100x sorgu hızlanması** |

### Değişiklik Yapılmazsa En Büyük Risk
- Sprint süresi uzadıkça (30+ gün) analytics endpoint'leri **lineer olarak yavaşlar** — burndown endpoint'i 30 günlük sprint için **~30 ayrı veritabanı sorgusu** çalıştırır.
- Proje büyüdükçe (>50 story, >200 task) policy kontrolleri istek başına **5-10 tekrarlı sorgu** oluşturarak latency'yi ciddi artırır.

---

## 2) Bulgular (Öncelik Sırasına Göre)

---

### Bulgu #1: CalculateBurndownAction — Döngü İçinde N+1 Sorgu

- **Kategori:** DB / Algoritma
- **Ciddiyet:** Kritik
- **Etki:** Latency, DB yükü, throughput
- **Kanıt:** [app/Actions/Analytics/CalculateBurndownAction.php](app/Actions/Analytics/CalculateBurndownAction.php#L37-L45) — `foreach ($period as $date)` döngüsü içinde her gün için ayrı `$sprint->userStories()->...->sum()` sorgusu yapılıyor.

```php
// Mevcut kod (L37-L45):
foreach ($period as $date) {
    if ($date->isFuture()) { $actualLine[] = null; continue; }

    $completedToday = $sprint->userStories()
        ->where('status', StoryStatus::Done)
        ->whereDate('updated_at', '<=', $date)
        ->sum('total_points');  // ← Her gün için ayrı sorgu!

    $actualLine[] = round($totalPoints - (float) $completedToday, 1);
}
```

- **Neden Verimsiz:** 30 günlük sprint = 30 ayrı `SELECT SUM(...)` sorgusu. Sprint uzunluğuyla doğru orantılı O(n) veritabanı çağrısı.
- **Önerilen Düzeltme:**

```php
// Tüm done story'leri tek sorguda al, bellek içinde filtrele
$doneStories = $sprint->userStories()
    ->where('status', StoryStatus::Done)
    ->select('id', 'total_points', 'updated_at')
    ->get();

foreach ($period as $date) {
    if ($date->isFuture()) { $actualLine[] = null; continue; }

    $completedPoints = $doneStories
        ->filter(fn ($s) => $s->updated_at->startOfDay()->lte($date))
        ->sum('total_points');

    $actualLine[] = round($totalPoints - (float) $completedPoints, 1);
}
```

- **Ödünleşimler / Riskler:** Sprint'te çok fazla story varsa bellek kullanımı artabilir, ancak tipik sprint boyutlarında (10-50 story) bu ihmal edilebilir düzeydedir.
- **Tahmini Etki:** ~30 query → 1 query. **15-30x hızlanma.**
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #2: CalculateVelocityAction — Sprint Map İçinde N+1 Sorgu

- **Kategori:** DB
- **Ciddiyet:** Yüksek
- **Etki:** Latency, DB yükü
- **Kanıt:** [app/Actions/Analytics/CalculateVelocityAction.php](app/Actions/Analytics/CalculateVelocityAction.php#L23-L28) — `$sprints->map()` içinde her sprint için ayrı `sum('total_points')` sorgusu.

```php
// Mevcut kod (L23-L28):
$sprintData = $sprints->map(function ($sprint) {
    $completedPoints = (float) $sprint->userStories()
        ->where('status', StoryStatus::Done)
        ->sum('total_points');  // ← Sprint başına 1 sorgu

    return ['name' => $sprint->name, 'completed_points' => $completedPoints];
})->toArray();
```

- **Neden Verimsiz:** 5 kapalı sprint = 5 ayrı SUM sorgusu. `sprintCount` arttıkça sorgu sayısı doğru orantılı artar.
- **Önerilen Düzeltme:**

```php
$sprints = $project->sprints()
    ->where('status', SprintStatus::Closed)
    ->orderByDesc('created_at')
    ->limit($sprintCount)
    ->withSum(['userStories as completed_points' => function ($q) {
        $q->where('status', StoryStatus::Done);
    }], 'total_points')
    ->get()
    ->reverse()
    ->values();

$sprintData = $sprints->map(fn ($sprint) => [
    'name' => $sprint->name,
    'completed_points' => (float) ($sprint->completed_points ?? 0),
])->toArray();
```

- **Ödünleşimler / Riskler:** Yok. Daha temiz ve performanslı.
- **Tahmini Etki:** n+1 sorgu → 1 sorgu. **5x hızlanma.**
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #3: 7 Policy'de Tekrarlanan getMemberRole() Sorguları

- **Kategori:** DB
- **Ciddiyet:** Yüksek
- **Etki:** Latency, DB yükü
- **Kanıt:** Aşağıdaki tüm policy dosyalarında birebir aynı `getMemberRole()` private metodu bulunuyor, her çağrıda ayrı veritabanı sorgusu yapıyor:
  - [ProjectPolicy.php](app/Policies/ProjectPolicy.php#L85-L91)
  - [TaskPolicy.php](app/Policies/TaskPolicy.php#L82-L86)
  - [UserStoryPolicy.php](app/Policies/UserStoryPolicy.php#L66-L70)
  - [IssuePolicy.php](app/Policies/IssuePolicy.php#L80-L84)
  - [SprintPolicy.php](app/Policies/SprintPolicy.php#L51-L55)
  - [EpicPolicy.php](app/Policies/EpicPolicy.php#L41-L45)
  - [AttachmentPolicy.php](app/Policies/AttachmentPolicy.php#L44-L48)

```php
// Her policy'de tekrarlanan metot:
private function getMemberRole(User $user, Project $project): ?ProjectRole
{
    return $user->projectMemberships()
        ->where('project_id', $project->id)
        ->first()?->role;   // ← Her policy çağrısında ayrı SELECT
}
```

- **Neden Verimsiz:**
  1. **Tekrarlanan kod:** Birebir aynı metot 7 dosyada copy-paste edilmiş.
  2. **Tekrarlanan sorgular:** Bir request'te birden fazla policy kontrolü yapıldığında (örn. `authorize('update', $story)` → UserStoryPolicy → getMemberRole + isAtLeast) her biri ayrı sorgu çalıştırır.
  3. `EnsureProjectMember` middleware'ı zaten membership'i `$request->attributes->set('membership', $membership)` ile önbelleğe alıyor ama policy'ler bunu kullanmıyor.

- **Önerilen Düzeltme:**

```php
// Seçenek A: Policy'lerde middleware cache'ini kullan
private function getMemberRole(User $user, Project $project): ?ProjectRole
{
    $cached = request()->attributes->get('membership');
    if ($cached && $cached->project_id === $project->id && $cached->user_id === $user->id) {
        return $cached->role;
    }

    return $user->projectMemberships()
        ->where('project_id', $project->id)
        ->first()?->role;
}

// Seçenek B (Daha iyi): Ortak bir trait oluştur
// app/Traits/ResolvesMembership.php
trait ResolvesMembership
{
    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
        $cached = request()->attributes->get('membership');
        if ($cached && $cached->project_id === $project->id && $cached->user_id === $user->id) {
            return $cached->role;
        }

        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first()?->role;
    }

    private function isAtLeast(User $user, Project $project, ProjectRole $minimumRole): bool
    {
        $role = $this->getMemberRole($user, $project);
        return $role !== null && $role->isAtLeast($minimumRole);
    }
}
```

- **Ödünleşimler / Riskler:** Request cache'i middleware'in çalışmadığı durumlarda stale veri dönebilir — fallback sorgusu eklendiği için güvenli.
- **Tahmini Etki:** İstek başına ~5-10 tekrarlanan sorgu → 0-1 sorguya düşer. **%80-90 azalma.**
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Servis geneli (7 policy)

---

### Bulgu #4: TaskPolicy'de N+1 — task->userStory->project Zinciri

- **Kategori:** DB
- **Ciddiyet:** Yüksek
- **Etki:** Latency
- **Kanıt:** [TaskPolicy.php](app/Policies/TaskPolicy.php#L36-L37) — `changeStatus`, `update`, `assign`, `delete` metotlarının hepsinde `$task->userStory->project` erişimi yapılıyor:

```php
// L36-37:
public function changeStatus(User $user, Task $task): bool
{
    $project = $task->userStory->project;  // ← 2 lazy load sorgusu: userStory + project
    ...
}
```

- **Neden Verimsiz:** `userStory` ve `project` ilişkileri eager-load edilmemiş. Her policy metodu çağrıldığında 2 sorgu + getMemberRole'den 1 sorgu = **3 sorgu per policy check.**
- **Önerilen Düzeltme:** Task controller'da authorize çağırmadan önce ilişkileri yükle:

```php
// TaskController'da:
$task->loadMissing('userStory.project');
$this->authorize('changeStatus', $task);
```

- **Ödünleşimler / Riskler:** Yok.
- **Tahmini Etki:** Policy çağrısı başına 2 gereksiz sorgu eliminasyonu.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Modül (controller katmanı)

---

### Bulgu #5: Task Model — getProjectAttribute Accessor'ü N+1 Riski

- **Kategori:** DB / Algoritma
- **Ciddiyet:** Yüksek
- **Etki:** Latency
- **Kanıt:** [app/Models/Task.php](app/Models/Task.php#L80-L83) — Custom accessor, her çağrıda `userStory` ilişkisini lazy-load eder:

```php
// L80-83:
public function getProjectAttribute(): ?Project
{
    return $this->userStory?->project;   // ← Her erişimde 2 sorgu (userStory + project)
}
```

- **Neden Verimsiz:** Bu accessor döngü içinde kullanıldığında (örn. bir story'nin tüm task'larını listelerken) her task için 2 ek sorgu oluşturur: `N tasks × 2 sorgu = 2N ek sorgu`.
- **Önerilen Düzeltme:** Accessor yerine eager loading kullanılması tavsiye edilir. Accessor kaldırılırsa mevcut kodu kırabileceğinden, en azından `userStory` ve `project` eager-load edilmeli:

```php
// Task listelerken:
$story->tasks()->with('userStory.project')->get();
```

- **Ödünleşimler / Riskler:** Accessor'ü kaldırmak mevcut kodu kırabilir — doğrulama gerekli.
- **Tahmini Etki:** Task listesinde N adet task varsa 2N sorgu → 2 sorgu.
- **Kaldırma Güvenliği:** Doğrulama Gerekli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #6: TaskStatusChanged Event — Broadcasting Sırasında N+1

- **Kategori:** DB / Network
- **Ciddiyet:** Yüksek
- **Etki:** Broadcast latency, worker performansı
- **Kanıt:** [app/Events/Scrum/TaskStatusChanged.php](app/Events/Scrum/TaskStatusChanged.php#L30-L33) — `broadcastOn()` metodu içinde eager-load edilmemiş ilişki zincirine erişim:

```php
// L30-33:
public function broadcastOn(): array
{
    return [
        new PrivateChannel("project.{$this->task->userStory->project_id}"),
        //                                ↑ userStory lazy-load!
    ];
}
```

- **Neden Verimsiz:** Task serialize edilip queue worker'da deserialize edildiğinde, `broadcastOn()` çağrıldığında `userStory` tekrar DB'den yüklenir. Her broadcast işlemi için 1 ek sorgu.
- **Önerilen Düzeltme:** Event dispatch öncesi eager-load:

```php
// TaskService::changeStatus() içinde (dispatch öncesi):
$task->loadMissing('userStory');
TaskStatusChanged::dispatch($task, $oldStatus->value, $newStatus->value, $user);

// VEYA event'te project_id'yi constructor'da sakla:
public function __construct(
    public readonly Task $task,
    public readonly string $oldStatus,
    public readonly string $newStatus,
    public readonly User $changedBy,
    public readonly string $projectId,  // ← Ek parametre
) {}
```

- **Ödünleşimler / Riskler:** Constructor değişikliği mevcut dispatch çağrılarını kırar — dikkatli refactor gerekli.
- **Tahmini Etki:** Broadcast başına 1 gereksiz sorgu eliminasyonu.
- **Kaldırma Güvenliği:** Muhtemelen Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #7: Eksik Veritabanı İndeksleri

- **Kategori:** DB
- **Ciddiyet:** Yüksek
- **Etki:** Sorgu performansı, scalability
- **Kanıt:** Migration dosyaları incelendiğinde aşağıdaki indeksler eksik:

| Tablo | Eksik İndeks | Kullanım Yeri | Etki |
|-------|-------------|---------------|------|
| `user_stories` | `created_by` | Story oluşturan filtrelemesi, policy kontrolleri | Yüksek |
| `user_stories` | `order` | `scopeOrdered()` — `ORDER BY order` | Orta |
| `tasks` | `created_by` | TaskPolicy — `$task->created_by === $user->id` kontrolleri | Yüksek |
| `tasks` | `status` (tekil) | `scopeByStatus()` filtrelemesi | Orta |
| `issues` | `created_by` | IssuePolicy — creator kontrolleri | Yüksek |
| `sprint_scope_changes` | `sprint_id` (tekil) | Burndown hesaplamasında scope changes sorgusu | Orta |

- **Neden Verimsiz:** Foreign key constraint otomatik indeks oluşturmaz (PostgreSQL hariç). Bu sütunlar WHERE koşullarında ve JOIN'lerde yoğun kullanılıyor ancak indekslenmemiş → full table scan.
- **Önerilen Düzeltme:**

```php
// Yeni migration:
Schema::table('user_stories', function (Blueprint $table) {
    $table->index('created_by');
    $table->index('order');
});

Schema::table('tasks', function (Blueprint $table) {
    $table->index('created_by');
    $table->index('status');
});

Schema::table('issues', function (Blueprint $table) {
    $table->index('created_by');
});

Schema::table('sprint_scope_changes', function (Blueprint $table) {
    $table->index('sprint_id');
});
```

- **Ödünleşimler / Riskler:** İndeksler yazma işlemlerini çok küçük ölçüde yavaşlatır (ihmal edilebilir). Disk alanı kullanımı artar (minimal).
- **Tahmini Etki:** Sorgu başına **10-100x** hızlanma (tam tablo taraması → indeks lookup).
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #8: Analytics Dashboard — Tekrarlanan COUNT Sorguları

- **Kategori:** DB
- **Ciddiyet:** Orta
- **Etki:** Latency
- **Kanıt:** [analytics-dashboard.blade.php](resources/views/livewire/analytics/analytics-dashboard.blade.php#L63-L87) — Livewire bileşeninde 5 ayrı `#[Computed]` property her biri ayrı COUNT sorgusu çalıştırıyor:

```php
#[Computed]
public function totalStories(): int { return $this->project->userStories()->count(); }

#[Computed]
public function completedStories(): int { return $this->project->userStories()->byStatus(StoryStatus::Done)->count(); }

#[Computed]
public function totalIssues(): int { return $this->project->issues()->count(); }

#[Computed]
public function openIssues(): int { return $this->project->issues()->open()->count(); }

#[Computed]
public function closedSprints(): int { return $this->project->sprints()->closed()->count(); }
```

- **Neden Verimsiz:** 5 ayrı COUNT sorgusu her sayfa yüklemesinde çalışır. Bunlar tek bir sorguda veya en azından birleştirilerek optimize edilebilir.
- **Önerilen Düzeltme:**

```php
#[Computed]
public function stats(): array
{
    $stories = $this->project->userStories()
        ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed
        ", [StoryStatus::Done->value])
        ->first();

    $issues = $this->project->issues()
        ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) as open_count
        ", [IssueStatus::Done->value])
        ->first();

    return [
        'total_stories' => (int) $stories->total,
        'completed_stories' => (int) $stories->completed,
        'total_issues' => (int) $issues->total,
        'open_issues' => (int) $issues->open_count,
        'closed_sprints' => $this->project->sprints()->closed()->count(),
    ];
}
```

- **Ödünleşimler / Riskler:** Okunabilirlik biraz azalır. Computed caching ile mevcut yaklaşım da kabul edilebilir.
- **Tahmini Etki:** 5 sorgu → 3 sorgu. **%40 azalma.**
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #9: Backlog Livewire — Eksik Eager Loading

- **Kategori:** DB
- **Ciddiyet:** Orta
- **Etki:** Latency
- **Kanıt:** [backlog.blade.php](resources/views/livewire/scrum/backlog.blade.php#L72-L78) — `with('epic', 'creator', 'tasks')` var ama `tasks.assignee` eksik:

```php
#[Computed]
public function stories(): mixed
{
    $query = $this->project->userStories()
        ->backlog()
        ->with('epic', 'creator', 'tasks')  // ← tasks.assignee eksik!
        ->ordered();
    ...
}
```

Blade template'te `task.assignee` erişimi görsel olarak yapılmıyorsa sorun yok, ancak `storyPoints` ilişkisi de yüklenmemiş ve story detail'e giderken ihtiyaç duyulabilir.

- **Neden Verimsiz:** Eğer backlog view'ında task assignee bilgisi gösteriliyorsa, her task için ayrı `assignee` lazy-load sorgusu olacaktır.
- **Önerilen Düzeltme:**

```php
->with(['epic', 'creator', 'tasks.assignee', 'storyPoints'])
```

- **Ödünleşimler / Riskler:** Gereksiz veri yüklemesi (ihtiyaç yoksa). Ama eager loading maliyeti çoğu durumda N+1'den çok daha düşüktür.
- **Tahmini Etki:** Task assignee gösteriliyorsa: N sorgu → 1 sorgu.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #10: Notification Listesi — Pagination Yok

- **Kategori:** Bellek / DB
- **Ciddiyet:** Orta
- **Etki:** Bellek kullanımı, latency
- **Kanıt:** [notification-bell.blade.php](resources/views/livewire/notification/notification-bell.blade.php#L49-L53) — `limit(20)` var ama tüm bildirimler (okunmuş + okunmamış) yükleniyor:

```php
#[Computed]
public function notifications(): \Illuminate\Support\Collection
{
    return Notification::where('user_id', auth()->id())
        ->latest()
        ->limit(20)  // ✅ İyi — limit var
        ->get();
}
```

Aynı dosyada `mount()` içinde:

```php
$this->unreadCount = Notification::where('user_id', auth()->id())
    ->unread()
    ->count();  // ← Ayrı COUNT sorgusu
```

- **Neden Verimsiz:** Her panel açılışında tüm bildirimlerin `COUNT(*)` + son 20'sinin `SELECT *` sorgusu yapılır. Bildirim sayısı çok yüksekse COUNT yavaşlayabilir. Ama `limit(20)` mevcut olduğu için ana liste sorgusu kabul edilebilir düzeyde.
- **Önerilen Düzeltme:** Mevcut haliyle kabul edilebilir. Performans sorunu oluşursa `unread` count'u Redis/cache ile tutulabilir.
- **Ödünleşimler / Riskler:** Cache ile tutulursa invalidation karmaşıklığı artar.
- **Tahmini Etki:** Düşük-Orta.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #11: Issue List — Sayfalama Yok (Unbounded Query)

- **Kategori:** Bellek / DB
- **Ciddiyet:** Orta
- **Etki:** Bellek, latency, scalability
- **Kanıt:** [issue-list.blade.php](resources/views/livewire/issues/issue-list.blade.php#L152-L161) — Tüm issue'lar `->get()` ile çekilip sayfada gösteriliyor:

```php
#[Computed]
public function issues(): mixed
{
    $query = $this->project->issues()->with(['assignee', 'creator']);
    // ... filtreler ...
    return $query->latest()->get();  // ← Sınırsız! Tüm issue'lar belleğe yüklenir
}
```

Ayrıca `counts` computed property'sinde 4 ayrı COUNT sorgusu:

```php
#[Computed]
public function counts(): array
{
    return [
        'total' => $this->project->issues()->count(),
        'open' => $this->project->issues()->open()->count(),
        'bugs' => $this->project->issues()->byType(IssueType::Bug)->count(),
        'critical' => $this->project->issues()->bySeverity(IssueSeverity::Critical)->count(),
    ];
}
```

- **Neden Verimsiz:** Proje büyüdükçe tüm issue'ların belleğe yüklenmesi ciddi bellek ve latency sorunu oluşturur. 4 ayrı COUNT sorgusu tekrar.
- **Önerilen Düzeltme:**

```php
// Sayfalama ekle:
return $query->latest()->paginate(25);

// COUNT'ları birleştir:
$counts = $this->project->issues()
    ->selectRaw("
        COUNT(*) as total,
        SUM(CASE WHEN status != 'done' THEN 1 ELSE 0 END) as open_count,
        SUM(CASE WHEN type = 'bug' THEN 1 ELSE 0 END) as bug_count,
        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count
    ")->first();
```

- **Ödünleşimler / Riskler:** UI'da sayfalama eklenmeli.
- **Tahmini Etki:** Büyük projelerde %60-80 bellek tasarrufu.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #12: Route Dosyasında Inline Controller Mantığı

- **Kategori:** Bakım Kolaylığı / Kod Tekrarı
- **Ciddiyet:** Orta
- **Etki:** Bakım maliyeti, test edilebilirlik
- **Kanıt:** [routes/api.php](routes/api.php#L115-L170) — Attachment, Notification ve Auth logout/me endpoint'leri inline closure olarak tanımlanmış (~60+ satır iş mantığı route dosyasında):

```php
// L115-140: Attachments
Route::post('attachments', function (Request $request) {
    $request->validate([...]);
    $modelMap = [...];
    $model = $modelMap[...]::findOrFail(...);
    $attachment = app(AttachmentService::class)->upload(...);
    return new AttachmentResource($attachment);
});

// L145-180: Notifications
Route::prefix('notifications')->group(function () {
    Route::get('/', function (Request $request) { ... });
    Route::post('/mark-read', function (Request $request) { ... });
    Route::post('/mark-all-read', function (Request $request) { ... });
});
```

- **Neden Verimsiz:** 
  1. Route dosyası okunabilirliği azalır.
  2. Bu closure'lar birim test edilemez (controller aksine).
  3. Authorization policy kontrolü eksik (attachment upload için `$this->authorize()` düzgün çağrılamaz).
- **Önerilen Düzeltme:** `AttachmentController` ve `NotificationController` oluşturulmalı.
- **Ödünleşimler / Riskler:** Yok — tamamen olumlu refactor.
- **Tahmini Etki:** Bakım kolaylığında iyileşme, test kapsamı artışı.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #13: Controller'larda Inline Validation (FormRequest Kullanılmamış)

- **Kategori:** Bakım Kolaylığı / Kod Tekrarı
- **Ciddiyet:** Düşük-Orta
- **Etki:** Bakım maliyeti, tutarsızlık
- **Kanıt:**
  - [UserStoryController::update()](app/Http/Controllers/Scrum/UserStoryController.php#L71-L76) — `request()->validate([...])` inline
  - [TaskController::update()](app/Http/Controllers/Scrum/TaskController.php#L42-L46) — `$request->validate([...])` inline
  - [SprintController::update()](app/Http/Controllers/Scrum/SprintController.php#L44-L48) — `request()->validate([...])` inline
  - [SprintController::reorder()](app/Http/Controllers/Scrum/UserStoryController.php#L110-L114) — `$request->validate([...])` inline

```php
// UserStoryController L71-76:
$story = $this->service->update($story, request()->validate([
    'title' => ['sometimes', 'required', 'string', 'max:255'],
    'description' => ['nullable', 'string', 'max:10000'],
    'epic_id' => ['nullable', 'string', 'exists:epics,id'],
]));
```

- **Neden Verimsiz:** Diğer controller'lar FormRequest pattern'i kullanırken bu birkaçı inline validation yapıyor → tutarsızlık. Authorization da FormRequest'te tanımlanabilecekken controller'da ayrı `authorize()` çağrısı gerekiyor.
- **Önerilen Düzeltme:** `UpdateUserStoryRequest`, `UpdateTaskRequest`, `UpdateSprintRequest` FormRequest'leri oluşturulmalı.
- **Ödünleşimler / Riskler:** Yok.
- **Tahmini Etki:** Kod tutarlılığında iyileşme.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Modül

---

### Bulgu #14: Ölü Kod — LogActivity ve BroadcastProjectUpdate Listener'ları

- **Kategori:** Ölü Kod
- **Ciddiyet:** Düşük
- **Etki:** Bakım maliyeti, kafa karışıklığı
- **Kanıt:**
  - [app/Listeners/LogActivity.php](app/Listeners/LogActivity.php#L15-L19) — `handle()` metodu tamamen boş yorum:

```php
public function handle(object $event): void
{
    // Event tipine göre action ve subject belirlenir.
    // Bu listener implement edildiğinde event inspection yapılacak.
}
```

  - [app/Listeners/BroadcastProjectUpdate.php](app/Listeners/BroadcastProjectUpdate.php#L16-L20) — Aynı şekilde boş:

```php
public function handle(object $event): void
{
    // Broadcasting implementasyonu
    // Laravel Echo + Reverb ile proje kanalına broadcast edilecek.
}
```

- **Neden Verimsiz:**
  1. EventServiceProvider'da kayıtlıysa her ilgili event tetiklendiğinde boş sınıf instantiate edilir.
  2. Bakımcılar için "bu çalışıyor mu, çalışmıyor mu?" belirsizliği.
- **Önerilen Düzeltme:**
  1. Eğer yakında implement edilecekse: `// TODO:` etiketi eklenip açık bırakılabilir.
  2. Kullanılmayacaksa: Listener'lar ve event binding'leri silinmeli.
- **Ödünleşimler / Riskler:** Gelecekte gerekebilir — `// TODO:` ile bırakılabilir.
- **Tahmini Etki:** Düşük ama bakım kolaylığında iyileşme.
- **Kaldırma Güvenliği:** Doğrulama Gerekli (EventServiceProvider'da binding var mı kontrol edilmeli)
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #15: Auditable Trait — Hiç Kullanılmayan Özellik

- **Kategori:** Ölü Kod
- **Ciddiyet:** Düşük
- **Etki:** Bakım maliyeti
- **Kanıt:** [app/Traits/Auditable.php](app/Traits/Auditable.php) — Bu trait `Task`, `UserStory`, `Project` model'lerinde use ediliyor, `ActivityLog` ilişkisi tanımlıyor ancak:
  1. `LogActivity` listener'ı boş (Bulgu #14).
  2. Hiçbir yerde `$model->activityLogs()` sorgusu veya yazma işlemi yok.
  3. `activity_logs` tablosuna hiçbir kayıt yazılmıyor.

- **Neden Verimsiz:** Kullanılmayan trait'ler ve migration'lar karmaşıklık ekler.
- **Önerilen Düzeltme:** Ya implementasyonu tamamla ya da kaldır.
- **Ödünleşimler / Riskler:** Gelecekte kullanılacaksa bırakılabilir.
- **Tahmini Etki:** Düşük.
- **Kaldırma Güvenliği:** Doğrulama Gerekli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #16: AddMemberAction — Verimsiz COUNT Sorgusu

- **Kategori:** DB
- **Ciddiyet:** Düşük
- **Etki:** Mikro-optimizasyon
- **Kanıt:** [app/Actions/Project/AddMemberAction.php](app/Actions/Project/AddMemberAction.php#L27) — Üye sayısı kontrolü için tam COUNT yapılıyor:

```php
if ($project->memberships()->count() >= self::MAX_MEMBERS) {
    throw new MaxMembersExceededException(...);
}
```

- **Neden Verimsiz:** `COUNT(*)` tüm kayıtları sayar. MAX_MEMBERS = 5 olduğundan, 6. kayıt var mı diye bakmak yeterli.
- **Önerilen Düzeltme:**

```php
$memberCount = $project->memberships()->limit(self::MAX_MEMBERS + 1)->count();
if ($memberCount >= self::MAX_MEMBERS) {
    throw new MaxMembersExceededException(...);
}
```

- **Ödünleşimler / Riskler:** MAX_MEMBERS = 5 ile etkisi minimal. Büyük projelerde daha anlamlı olur.
- **Tahmini Etki:** Düşük (MAX_MEMBERS küçük).
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #17: SprintController::index — Eager Loading ve Sayfalama Eksik

- **Kategori:** DB / Bellek
- **Ciddiyet:** Düşük-Orta
- **Etki:** Scalability
- **Kanıt:** [SprintController.php](app/Http/Controllers/Scrum/SprintController.php#L22-L24):

```php
public function index(Project $project): AnonymousResourceCollection
{
    return SprintResource::collection($project->sprints()->latest()->get());
    //                                                          ↑ Sayfalama yok
}
```

- **Neden Verimsiz:** Tüm sprint'ler çekilir. Projenin 50+ sprint'i olduğunda gereksiz bellek kullanımı. Sprint resource'unda `whenLoaded()` ile ilişkiler varsa N+1 oluşabilir.
- **Önerilen Düzeltme:**

```php
return SprintResource::collection(
    $project->sprints()->latest()->paginate($request->integer('per_page', 20))
);
```

- **Ödünleşimler / Riskler:** Frontend'de sayfalama uyumluluğu gerekir.
- **Tahmini Etki:** Düşük-Orta.
- **Kaldırma Güvenliği:** Güvenli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #18: Dashboard Livewire — Gereksiz İç İçe Eager Loading

- **Kategori:** DB
- **Ciddiyet:** Düşük
- **Etki:** Gereksiz veri yükleme
- **Kanıt:** [dashboard.blade.php](resources/views/livewire/dashboard.blade.php#L22-L27):

```php
#[Computed]
public function projects(): mixed
{
    return auth()->user()
        ->projectMemberships()
        ->with('project.owner', 'project.memberships')  // ← project.memberships gerekli mi?
        ->get()
        ->pluck('project')
        ->filter();
}
```

- **Neden Verimsiz:** `project.memberships` tüm üyelik kayıtlarını her proje için yükler. Dashboard'da sadece üye sayısı gösteriliyorsa `withCount('memberships')` daha verimli olur.
- **Önerilen Düzeltme:**

```php
->with(['project' => function ($q) {
    $q->with('owner')->withCount('memberships');
}])
```

- **Ödünleşimler / Riskler:** Dashboard'da üye detayı gösteriliyorsa `with('memberships')` gerekebilir.
- **Tahmini Etki:** Düşük.
- **Kaldırma Güvenliği:** Doğrulama Gerekli
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #19: Service Katmanında fresh() Kullanımı

- **Kategori:** DB
- **Ciddiyet:** Düşük
- **Etki:** Gereksiz sorgu
- **Kanıt:** Birden fazla service'te `update()` sonrası `fresh()` çağrısı:
  - [UserStoryService::update()](app/Services/UserStoryService.php#L49-L53)
  - [TaskService::update()](app/Services/TaskService.php#L35-L39)
  - [TaskService::assign()](app/Services/TaskService.php#L62-L63)
  - [SprintService::update()](app/Services/SprintService.php#L36-L38)
  - [MembershipService::changeRole()](app/Services/MembershipService.php#L56-L58)

```php
// Örnek:
public function update(UserStory $story, array $data): UserStory
{
    $story->update($data);
    return $story->fresh();  // ← DB'den tekrar yükleme — genellikle gereksiz
}
```

- **Neden Verimsiz:** `update()` zaten model'in in-memory state'ini günceller. `fresh()` çağrısı DB'den tüm sütunları tekrar yükler. Çoğu durumda `$story->refresh()` (tek sorgu, aynı modeli günceller) veya doğrudan `return $story` yeterlidir.
- **Önerilen Düzeltme:**

```php
public function update(UserStory $story, array $data): UserStory
{
    $story->update($data);
    return $story;  // fresh() yerine
}
```

- **Ödünleşimler / Riskler:** Eğer mutator/accessor veya default value davranışları varsa `fresh()` gerekli olabilir. Her case için doğrulama gerekli.
- **Tahmini Etki:** Update başına 1 gereksiz SELECT eliminasyonu.
- **Kaldırma Güvenliği:** Muhtemelen Güvenli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #20: Thin Wrapper Service'ler — Gereksiz Soyutlama

- **Kategori:** Aşırı Soyutlama
- **Ciddiyet:** Düşük
- **Etki:** Bakım maliyeti
- **Kanıt:**
  - [VelocityService.php](app/Services/VelocityService.php) — 17 satır, sadece Action'ı çağırır:

```php
class VelocityService
{
    public function __construct(private CalculateVelocityAction $action) {}

    public function getVelocityData(Project $project, int $sprintCount = 5): array
    {
        return $this->action->execute($project, $sprintCount);
    }
}
```

  - [BurndownService.php](app/Services/BurndownService.php) — 17 satır, sadece Action'ı çağırır:

```php
class BurndownService
{
    public function __construct(private CalculateBurndownAction $action) {}

    public function getBurndownData(Sprint $sprint): array
    {
        return $this->action->execute($sprint);
    }
}
```

- **Neden Verimsiz:** Service hiçbir ek değer katmıyor (transaction wrapping, event dispatch, error handling yok). Sırf mimari pattern için var. Controller veya Livewire doğrudan Action'ı çağırabilir.
- **Önerilen Düzeltme:** Bu service'leri kaldırıp controller/Livewire'dan doğrudan Action inject et. **VEYA** gelecekte caching/rate-limiting eklenecekse koruyun.
- **Ödünleşimler / Riskler:** Mimari tutarlılık bozulabilir. Gelecekte eklenmesi gereken logic olursa service'i yeniden oluşturmak gerekir.
- **Tahmini Etki:** Çok düşük — sadece bakım kolaylığı.
- **Kaldırma Güvenliği:** Doğrulama Gerekli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #21: Attachment Upload — Authorization Kontrolü Eksik

- **Kategori:** Güvenlik Etkili Verimsizlik
- **Ciddiyet:** Orta
- **Etki:** Güvenlik, suistimal vektörü
- **Kanıt:** [routes/api.php](routes/api.php#L115-L140) — Attachment upload route'unda Policy kontrolü yok:

```php
Route::post('attachments', function (Request $request) {
    $request->validate([
        'attachable_type' => ['required', 'string', 'in:user_story,task,issue'],
        'attachable_id' => ['required', 'string'],
        'file' => ['required', 'file', 'max:10240'],
    ]);

    $model = $modelMap[...]::findOrFail(...);
    // ← $this->authorize('create', ...) EKSIK!
    // Herhangi bir authenticate kullanıcı herhangi bir entity'ye dosya yükleyebilir
});
```

- **Neden Verimsiz:** Herhangi bir authenticate kullanıcı, üyesi olmadığı bir projenin entity'sine dosya yükleyebilir. Bu bir güvenlik açığıdır.
- **Önerilen Düzeltme:** Controller'a taşı ve `$this->authorize('create', [Attachment::class, $project])` çağrısı ekle.
- **Ödünleşimler / Riskler:** Mevcut çalışan istekleri kırabilir (yetkisiz olanlar).
- **Tahmini Etki:** Güvenlik düzeltmesi — performans değil.
- **Kaldırma Güvenliği:** Doğrulama Gerekli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #22: Attachment Silme — Dosya Yetkilendirme Kontrolü Eksik

- **Kategori:** Güvenlik Etkili Verimsizlik
- **Ciddiyet:** Orta
- **Etki:** Güvenlik
- **Kanıt:** [routes/api.php](routes/api.php#L142-L146) — Attachment delete route'unda da Policy kontrolü yok:

```php
Route::delete('attachments/{attachment}', function (Attachment $attachment, Request $request) {
    app(AttachmentService::class)->delete($attachment);
    // ← authorize() EKSIK! Herkes herhangi bir dosyayı silebilir
    return response()->json(null, 204);
});
```

- **Neden Verimsiz:** `AttachmentPolicy::delete()` tanımlı ama burada kullanılmıyor.
- **Önerilen Düzeltme:** Controller'a taşı ve `$this->authorize('delete', $attachment)` ekle.
- **Ödünleşimler / Riskler:** Yok.
- **Tahmini Etki:** Güvenlik düzeltmesi.
- **Kaldırma Güvenliği:** Doğrulama Gerekli
- **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #23: UserStoryService::delete() — Cascade Olmasına Rağmen Manuel Silme

- **Kategori:** Kod Tekrarı
- **Ciddiyet:** Düşük
- **Etki:** Bakım maliyeti
- **Kanıt:** [UserStoryService.php](app/Services/UserStoryService.php#L56-L60):

```php
public function delete(UserStory $story): void
{
    $story->tasks()->delete();        // ← Migration'da cascadeOnDelete var!
    $story->storyPoints()->delete();  // ← Migration'da cascadeOnDelete var!
    $story->delete();
}
```

  Migration'larda:
  - `tasks` tablosu: `foreignUuid('user_story_id')->constrained()->cascadeOnDelete()` ✅
  - `story_points` tablosu: Kontrol gerekli

- **Neden Verimsiz:** Cascade zaten DB seviyesinde çalışır. Manuel silme hem gereksiz sorgu hem de cascade ile çakışma riski.
- **Önerilen Düzeltme:** story_points'te de cascade varsa:

```php
public function delete(UserStory $story): void
{
    $story->delete();  // Cascade otomatik temizler
}
```

- **Ödünleşimler / Riskler:** story_points tablosunda cascade olduğunu doğrula.
- **Tahmini Etki:** 2 gereksiz DELETE sorgusu eliminasyonu.
- **Kaldırma Güvenliği:** Doğrulama Gerekli (cascade varlığı kontrol edilmeli)
- **Yeniden Kullanım Kapsamı:** Lokal dosya

---

## 3) Hızlı Kazanımlar (Önce Bunları Yap)

| # | İyileştirme | Süre Tahmini | Etki |
|---|-------------|-------------|------|
| 1 | **CalculateBurndownAction** — Döngü içi sorguyu tek sorguya indirge (Bulgu #1) | ~15 dakika | **Kritik** |
| 2 | **CalculateVelocityAction** — `withSum()` kullan (Bulgu #2) | ~10 dakika | **Yüksek** |
| 3 | **Eksik indeksleri ekle** — Tek migration dosyası (Bulgu #7) | ~10 dakika | **Yüksek** |
| 4 | **TaskStatusChanged event'inde eager-load** (Bulgu #6) | ~5 dakika | **Yüksek** |
| 5 | **TaskController'da `loadMissing('userStory.project')`** (Bulgu #4) | ~5 dakika | **Yüksek** |
| 6 | **Backlog'da eager loading genişlet** (Bulgu #9) | ~5 dakika | **Orta** |
| 7 | **Attachment route'larına authorization ekle** (Bulgu #21, #22) | ~15 dakika | **Güvenlik** |

---

## 4) Derin Optimizasyonlar (Sonra Bunları Yap)

| # | İyileştirme | Açıklama |
|---|-------------|----------|
| 1 | **Policy trait'i oluştur** (Bulgu #3) | 7 policy'deki `getMemberRole()` + `isAtLeast()` tekrarını trait'e taşı, middleware cache'i entegre et |
| 2 | **Inline route mantığını controller'a taşı** (Bulgu #12) | `AttachmentController` ve `NotificationController` oluştur |
| 3 | **FormRequest'leri tamamla** (Bulgu #13) | `UpdateUserStoryRequest`, `UpdateTaskRequest`, `UpdateSprintRequest` ekle |
| 4 | **Issue list'e sayfalama ekle** (Bulgu #11) | Livewire bileşeninde `paginate()` + blade'de pagination links |
| 5 | **Analytics dashboard COUNT'ları birleştir** (Bulgu #8) | 5 ayrı sorguyu 2-3 sorguya düşür |
| 6 | **Ölü listener'ları temizle veya implement et** (Bulgu #14, #15) | `LogActivity`, `BroadcastProjectUpdate` kararı verilmeli |
| 7 | **Thin service'leri değerlendir** (Bulgu #20) | `VelocityService` ve `BurndownService` gerekliliğini gözden geçir |

---

## 5) Doğrulama Planı

### Benchmark'lar

1. **Burndown endpoint:** Optimize öncesi ve sonrası `php artisan tinker` veya test ile `DB::getQueryLog()` karşılaştır:

```php
DB::enableQueryLog();
$action = new CalculateBurndownAction();
$action->execute($sprint);
$queries = DB::getQueryLog();
echo count($queries); // Önce: ~32, Sonra: ~3
```

2. **Velocity endpoint:** Aynı yöntemle:

```php
DB::enableQueryLog();
$action = new CalculateVelocityAction();
$action->execute($project, 5);
echo count(DB::getQueryLog()); // Önce: ~7, Sonra: ~2
```

### Profiling Stratejisi

1. **Laravel Debugbar / Telescope** ekle (geliştirme ortamında):
   - Sayfa başına toplam sorgu sayısını izle
   - N+1 uyarılarını aktif et: `Model::preventLazyLoading()` (development'ta)

2. **`preventLazyLoading` Aktifleştirmesi:**

```php
// AppServiceProvider::boot()
if (app()->isLocal()) {
    Model::preventLazyLoading();
}
```

Bu, tüm N+1 sorunlarını development'ta exception olarak yakalayacaktır.

### Karşılaştırma Metrikleri

| Metrik | Optimize Öncesi | Optimize Sonrası (Hedef) |
|--------|----------------|--------------------------|
| Burndown endpoint sorgu sayısı | ~32 | ≤3 |
| Velocity endpoint sorgu sayısı | ~7 | ≤2 |
| Backlog sayfa yükleme sorgu sayısı (50 story) | ~55+ | ≤8 |
| Policy kontrolü başına sorgu | 1-3 | 0-1 |
| Analytics dashboard sorgu sayısı | ~10 | ≤5 |

### Test Doğrulaması

Her değişiklik sonrası mevcut test suite'in geçtiğini doğrula:

```bash
php artisan test --compact
```

Ayrıca önerilen sorgu sayısı assert'leri ekle:

```php
// Örnek test:
public function test_burndown_uses_minimal_queries(): void
{
    $sprint = Sprint::factory()->hasUserStories(10)->create();

    $this->assertDatabaseQueryCount(3, function () use ($sprint) {
        (new CalculateBurndownAction())->execute($sprint);
    });
}
```

---

## 6) Optimize Edilmiş Kod / Yamalar

### Yama #1: CalculateBurndownAction

```php
// app/Actions/Analytics/CalculateBurndownAction.php
// DEĞİŞİKLİK: L37-L45 arasındaki foreach döngüsü

// ESKİ:
foreach ($period as $date) {
    if ($date->isFuture()) { $actualLine[] = null; continue; }
    $completedToday = $sprint->userStories()
        ->where('status', StoryStatus::Done)
        ->whereDate('updated_at', '<=', $date)
        ->sum('total_points');
    $actualLine[] = round($totalPoints - (float) $completedToday, 1);
}

// YENİ:
$doneStories = $sprint->userStories()
    ->where('status', StoryStatus::Done)
    ->select('id', 'total_points', 'updated_at')
    ->get();

foreach ($period as $date) {
    if ($date->isFuture()) { $actualLine[] = null; continue; }
    $completedPoints = $doneStories
        ->filter(fn ($s) => $s->updated_at->startOfDay()->lte($date))
        ->sum('total_points');
    $actualLine[] = round($totalPoints - (float) $completedPoints, 1);
}
```

### Yama #2: CalculateVelocityAction

```php
// app/Actions/Analytics/CalculateVelocityAction.php
// DEĞİŞİKLİK: L15-L30

// ESKİ:
$sprints = $project->sprints()
    ->where('status', SprintStatus::Closed)
    ->orderByDesc('created_at')
    ->limit($sprintCount)
    ->get()
    ->reverse()
    ->values();

$sprintData = $sprints->map(function ($sprint) {
    $completedPoints = (float) $sprint->userStories()
        ->where('status', StoryStatus::Done)
        ->sum('total_points');
    return ['name' => $sprint->name, 'completed_points' => $completedPoints];
})->toArray();

// YENİ:
$sprints = $project->sprints()
    ->where('status', SprintStatus::Closed)
    ->orderByDesc('created_at')
    ->limit($sprintCount)
    ->withSum(['userStories as completed_points' => function ($q) {
        $q->where('status', StoryStatus::Done);
    }], 'total_points')
    ->get()
    ->reverse()
    ->values();

$sprintData = $sprints->map(fn ($sprint) => [
    'name' => $sprint->name,
    'completed_points' => (float) ($sprint->completed_points ?? 0),
])->toArray();
```

### Yama #3: Eksik İndeksler Migration'ı

```php
// database/migrations/xxxx_xx_xx_xxxxxx_add_missing_indexes.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_stories', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('order');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('status');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('sprint_scope_changes', function (Blueprint $table) {
            $table->index('sprint_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_stories', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['order']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['status']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('sprint_scope_changes', function (Blueprint $table) {
            $table->dropIndex(['sprint_id']);
        });
    }
};
```

### Yama #4: Policy Trait (ResolvesMembership)

```php
// app/Traits/ResolvesMembership.php

<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

trait ResolvesMembership
{
    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
        $cached = request()->attributes->get('membership');

        if ($cached && $cached->project_id === $project->id && $cached->user_id === $user->id) {
            return $cached->role;
        }

        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first()?->role;
    }

    private function isAtLeast(User $user, Project $project, ProjectRole $minimumRole): bool
    {
        $role = $this->getMemberRole($user, $project);

        return $role !== null && $role->isAtLeast($minimumRole);
    }
}
```

---

## Genel Puan Tablosu

| Kategori | Puan | Notlar |
|----------|------|--------|
| **Mimari** | 9/10 | Mükemmel Action/Service/Controller ayrımı |
| **Kod Organizasyonu** | 8/10 | İyi — birkaç tutarsızlık (inline validation, inline routes) |
| **Performans** | 5/10 | Birden fazla N+1 sorgusu, döngü içi sorgular |
| **Test Kapsamı** | 8/10 | İyi kapsama — sorgu sayısı assertion'ları eksik |
| **Güvenlik** | 7/10 | İyi RBAC — Attachment endpoint'lerinde yetkilendirme eksik |
| **Veritabanı Tasarımı** | 7/10 | İyi şema — bazı indeksler eksik |
| **Ölü Kod** | 6/10 | Birkaç boş listener, kullanılmayan trait |
| **Scalability** | 6/10 | Sayfalama eksik noktalar (issue list, sprint list) |

**Genel: 7/10 — Sağlam temel, hedefli optimizasyonlarla önemli iyileştirmeler mümkün.**
