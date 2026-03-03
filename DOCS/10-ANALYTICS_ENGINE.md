# 10 — Analytics Engine

Burndown chart, velocity hesaplama, günlük snapshot mekanizması ve analitik formülleri.

**İlişkili Dokümanlar:** [Business Rules](./04-BUSINESS_RULES.md) | [Database Schema](./06-DATABASE_SCHEMA.md) | [API Design](./07-API_DESIGN.md)

---

## 1. Genel Bakış

Analytics Engine iki temel metriği hesaplar:
1. **Burndown Chart** — Sprint içindeki kalan işin günlük takibi
2. **Velocity** — Tamamlanan sprint'lerin ortalama story point kapasitesi

Her iki metrik de **SnapshotDailyBurndownAction** ile günlük snapshot'lanır ve geçmişe dönük veri sunar.

---

## 2. Burndown Chart

### 2.1 Veri Modeli

```
Sprint
  ├── total_points (sprint başlangıcındaki toplam SP)
  ├── start_date
  ├── end_date
  └── stories[] (sprint'e atanmış user stories)

SprintScopeChange (sprint_scope_changes tablosu)
  ├── sprint_id
  ├── story_id (nullable — scope ekleme/çıkarma)
  ├── change_type: added | removed
  ├── points_changed: +/- integer
  └── changed_at: date
```

### 2.2 Formüller

#### İdeal Çizgi (Ideal Burndown Line)

```
ideal_remaining(day) = total_points - (total_points / total_days) × elapsed_days
```

- `total_points` = Sprint başlangıcındaki toplam SP
- `total_days` = `end_date - start_date` (iş günü)
- `elapsed_days` = `day - start_date`

#### Gerçek Çizgi (Actual Burndown Line)

```
actual_remaining(day) = total_points
                        + Σ scope_additions(up to day)
                        - Σ scope_removals(up to day)
                        - Σ completed_points(up to day)
```

- `scope_additions` = SprintScopeChange `added` kayıtlarının toplamı
- `scope_removals` = SprintScopeChange `removed` kayıtlarının toplamı
- `completed_points` = O güne kadar `done` statüsüne geçen story'lerin SP toplamı

#### Scope Change Etkisi

Scope change olduğunda ideal çizgi **yeniden hesaplanmaz**. Bu, sprint başlangıcındaki tahmin ile gerçeklik arasındaki farkı görselleştirir.

### 2.3 Action Implementasyonu

```php
// app/Actions/Analytics/CalculateBurndownAction.php
class CalculateBurndownAction
{
    public function execute(Sprint $sprint): array
    {
        $startDate  = Carbon::parse($sprint->start_date);
        $endDate    = Carbon::parse($sprint->end_date);
        $totalDays  = $startDate->diffInDays($endDate);
        $totalPoints = $sprint->total_points;

        $scopeChanges = $sprint->scopeChanges()
            ->orderBy('changed_at')
            ->get()
            ->groupBy(fn ($c) => $c->changed_at->toDateString());

        $completedByDay = $sprint->stories()
            ->where('status', StoryStatus::Done)
            ->get()
            ->groupBy(fn ($s) => $s->updated_at->toDateString());

        $data = [];
        $cumulativeScope = 0;
        $cumulativeCompleted = 0;

        $current = $startDate->copy();
        $dayIndex = 0;

        while ($current->lte($endDate)) {
            $dateKey = $current->toDateString();

            // Scope değişiklikleri
            if (isset($scopeChanges[$dateKey])) {
                foreach ($scopeChanges[$dateKey] as $change) {
                    $cumulativeScope += $change->points_changed;
                }
            }

            // Tamamlanan story'ler
            if (isset($completedByDay[$dateKey])) {
                $cumulativeCompleted += $completedByDay[$dateKey]
                    ->sum(fn ($s) => $s->storyPoints()->sum('points'));
            }

            $data[] = [
                'date'      => $dateKey,
                'ideal'     => round($totalPoints - ($totalPoints / $totalDays) * $dayIndex, 1),
                'actual'    => $totalPoints + $cumulativeScope - $cumulativeCompleted,
                'scope'     => $cumulativeScope,
                'completed' => $cumulativeCompleted,
            ];

            $current->addDay();
            $dayIndex++;
        }

        return $data;
    }
}
```

### 2.4 Response Format

```json
{
  "sprint_id": "01HQ...",
  "sprint_name": "Sprint 3",
  "total_points": 34,
  "data": [
    { "date": "2025-01-06", "ideal": 34.0, "actual": 34, "scope": 0, "completed": 0 },
    { "date": "2025-01-07", "ideal": 30.6, "actual": 29, "scope": 0, "completed": 5 },
    { "date": "2025-01-08", "ideal": 27.2, "actual": 31, "scope": 5, "completed": 8 },
    { "date": "2025-01-09", "ideal": 23.8, "actual": 23, "scope": 5, "completed": 16 },
    { "date": "2025-01-10", "ideal": 20.4, "actual": 18, "scope": 5, "completed": 21 }
  ]
}
```

---

## 3. Velocity

### 3.1 Formül

```
velocity = Σ completed_points(sprint[i]) / N
```

- Son N tamamlanmış sprint'in ortalaması (varsayılan N=5)
- Sadece `closed` statüsündeki sprint'ler dahil
- `completed_points(sprint)` = O sprint'te `done` statüsüne geçen story'lerin toplam SP'si

### 3.2 Action Implementasyonu

```php
// app/Actions/Analytics/CalculateVelocityAction.php
class CalculateVelocityAction
{
    private const DEFAULT_SPRINT_COUNT = 5;

    public function execute(Project $project, int $sprintCount = self::DEFAULT_SPRINT_COUNT): array
    {
        $closedSprints = Sprint::where('project_id', $project->id)
            ->where('status', SprintStatus::Closed)
            ->orderByDesc('end_date')
            ->take($sprintCount)
            ->get();

        $sprintVelocities = $closedSprints->map(function (Sprint $sprint) {
            $completedPoints = $sprint->stories()
                ->where('status', StoryStatus::Done)
                ->get()
                ->sum(fn ($s) => $s->storyPoints()->sum('points'));

            return [
                'sprint_id'   => $sprint->id,
                'sprint_name' => $sprint->name,
                'end_date'    => $sprint->end_date,
                'points'      => $completedPoints,
            ];
        });

        $average = $sprintVelocities->count() > 0
            ? round($sprintVelocities->avg('points'), 1)
            : 0;

        return [
            'project_id'      => $project->id,
            'average_velocity' => $average,
            'sprint_count'     => $sprintVelocities->count(),
            'sprints'          => $sprintVelocities->toArray(),
        ];
    }
}
```

### 3.3 Response Format

```json
{
  "project_id": "01HQ...",
  "average_velocity": 28.4,
  "sprint_count": 5,
  "sprints": [
    { "sprint_id": "01HQ...", "sprint_name": "Sprint 5", "end_date": "2025-02-21", "points": 34 },
    { "sprint_id": "01HQ...", "sprint_name": "Sprint 4", "end_date": "2025-02-07", "points": 29 },
    { "sprint_id": "01HQ...", "sprint_name": "Sprint 3", "end_date": "2025-01-24", "points": 31 },
    { "sprint_id": "01HQ...", "sprint_name": "Sprint 2", "end_date": "2025-01-10", "points": 25 },
    { "sprint_id": "01HQ...", "sprint_name": "Sprint 1", "end_date": "2024-12-27", "points": 23 }
  ]
}
```

---

## 4. Günlük Snapshot Mekanizması

### 4.1 Neden Snapshot?

Burndown verisini her seferinde gerçek zamanlı hesaplamak maliyetli olabilir. Günlük snapshot ile:
- Geçmiş veriler korunur (story silinse bile)
- API response hızlı olur (önceden hesaplanmış)
- Scope change geçmişi doğru yansır

### 4.2 Snapshot Action

```php
// app/Actions/Analytics/SnapshotDailyBurndownAction.php
class SnapshotDailyBurndownAction
{
    public function __construct(
        private CalculateBurndownAction $burndownAction,
    ) {}

    public function execute(): void
    {
        // Aktif sprint'leri bul
        $activeSprints = Sprint::where('status', SprintStatus::Active)->get();

        foreach ($activeSprints as $sprint) {
            $burndownData = $this->burndownAction->execute($sprint);
            $today = now()->toDateString();

            // Bugünkü veriyi kaydet
            $todayData = collect($burndownData)->firstWhere('date', $today);

            if ($todayData) {
                DB::table('burndown_snapshots')->updateOrInsert(
                    [
                        'sprint_id' => $sprint->id,
                        'date'      => $today,
                    ],
                    [
                        'ideal_remaining'  => $todayData['ideal'],
                        'actual_remaining' => $todayData['actual'],
                        'scope_change'     => $todayData['scope'],
                        'completed_points' => $todayData['completed'],
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]
                );
            }
        }
    }
}
```

### 4.3 Scheduler Kaydı

```php
// app/Console/Kernel.php (veya Laravel 11 routes/console.php)
Schedule::call(function () {
    app(SnapshotDailyBurndownAction::class)->execute();
})->dailyAt('23:55')->name('burndown-snapshot');
```

### 4.4 Snapshot Tablosu

```sql
-- burndown_snapshots tablosu (opsiyonel, MVP sonrası)
CREATE TABLE burndown_snapshots (
    id              BIGSERIAL PRIMARY KEY,
    sprint_id       CHAR(26) NOT NULL REFERENCES sprints(id) ON DELETE CASCADE,
    date            DATE NOT NULL,
    ideal_remaining DECIMAL(8,1) NOT NULL,
    actual_remaining INTEGER NOT NULL,
    scope_change    INTEGER NOT NULL DEFAULT 0,
    completed_points INTEGER NOT NULL DEFAULT 0,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP,
    UNIQUE(sprint_id, date)
);
```

---

## 5. Service Katmanı Entegrasyonu

```php
// app/Services/BurndownService.php
class BurndownService
{
    public function __construct(
        private CalculateBurndownAction $burndownAction,
    ) {}

    public function getBurndownData(Sprint $sprint): array
    {
        return $this->burndownAction->execute($sprint);
    }
}

// app/Services/VelocityService.php
class VelocityService
{
    public function __construct(
        private CalculateVelocityAction $velocityAction,
    ) {}

    public function getVelocity(Project $project, int $sprintCount = 5): array
    {
        return $this->velocityAction->execute($project, $sprintCount);
    }
}
```

---

## 6. Akış Diyagramı

```
Sprint Active
    │
    ├── Her gün 23:55 ─→ SnapshotDailyBurndownAction
    │                         │
    │                         ├── CalculateBurndownAction.execute()
    │                         └── burndown_snapshots INSERT
    │
    ├── Story tamamlanınca ─→ StoryStatusChanged Event
    │                              │
    │                              └── (gerçek zamanlı güncelleme)
    │
    ├── Scope change ─→ SprintScopeChanged Event
    │                        │
    │                        └── sprint_scope_changes INSERT
    │
    └── Sprint kapanınca ─→ Velocity hesaplamaya dahil
```

---

## 7. Livewire Entegrasyonu

```php
// app/Livewire/Analytics/BurndownChart.php
class BurndownChart extends Component
{
    public Sprint $sprint;
    public array $chartData = [];

    public function mount(Sprint $sprint)
    {
        $this->sprint = $sprint;
        $this->loadData();
    }

    public function loadData()
    {
        $this->chartData = app(BurndownService::class)
            ->getBurndownData($this->sprint);
    }

    // WebSocket ile gerçek zamanlı güncelleme
    #[On('echo:project.{sprint.project_id},SprintScopeChanged')]
    #[On('echo:project.{sprint.project_id},StoryStatusChanged')]
    public function refresh()
    {
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.analytics.burndown-chart');
    }
}
```

Frontend'de Chart.js veya Alpine.js ile çizim yapılır. Livewire component data sağlar, render client-side'da gerçekleşir.

---

**Önceki:** [09-INFRASTRUCTURE.md](./09-INFRASTRUCTURE.md)
**Sonraki:** [11-NOTIFICATION_SYSTEM.md](./11-NOTIFICATION_SYSTEM.md)
