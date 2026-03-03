# 14 — Coding Standards

Zorunlu kurallar, yasaklar, kodlama konvansiyonları, review checklist ve katman ihlal kontrolleri.

**İlişkili Dokümanlar:** [Architecture Overview](./01-ARCHITECTURE_OVERVIEW.md) | [Project Structure](./08-PROJECT_STRUCTURE.md) | [Testing Strategy](./13-TESTING_STRATEGY.md)

---

## 1. Kaynak Kurallar

Bu doküman iki zorunlu kaynak dosyaya dayanır:

1. **03-B-architecture-layers.md** — Service + Action katman mimarisi kuralları
2. **projeKuralları** — Backend ödevi zorunlu kuralları

Bu dosyalardaki tüm kurallar **bağlayıcıdır**. Aşağıda derlenen kurallar bu kaynaklardan çıkarılmıştır.

---

## 2. Zorunlu Kurallar (MUST)

### 2.1 Katman Kuralları

| # | Kural | Açıklama |
|---|-------|----------|
| K-01 | Controller SADECE Service çağırır | Controller'da business logic, direkt Model erişimi YASAK |
| K-02 | Service, Action'ları koordine eder | Transaction yönetimi, Event dispatch Service'te |
| K-03 | Action tek sorumluluk | Tek `execute()` metodu, tek iş yapar |
| K-04 | Layer skipping YASAK | Controller → Action (❌), Controller → Model (❌) |
| K-05 | Model::create() Controller'da YASAK | Her zaman Service → Action zinciri |
| K-06 | Business logic Livewire'da YASAK | Component sadece Service çağırır |

### 2.2 Validation Kuralları

| # | Kural | Açıklama |
|---|-------|----------|
| V-01 | FormRequest ZORUNLU | Controller'da inline `$request->validate()` YASAK |
| V-02 | Her endpoint'in FormRequest'i olmalı | Store, Update, özel action'lar dahil |
| V-03 | authorize() Policy çağırmalı | `return $this->user()->can(...)` |

### 2.3 Yetkilendirme Kuralları

| # | Kural | Açıklama |
|---|-------|----------|
| Y-01 | Policy ZORUNLU | Her entity için Policy tanımı |
| Y-02 | Hard-coded ID YASAK | `if ($user->id === 1)` gibi kontroller YASAK |
| Y-03 | Role hiyerarşisi enum ile | `ProjectRole::rank()` kullan |
| Y-04 | Middleware ile proje üyelik kontrolü | `EnsureProjectMember` middleware |

### 2.4 Model Kuralları

| # | Kural | Açıklama |
|---|-------|----------|
| M-01 | Query Scope ZORUNLU | Tekrar eden sorgular scope olmalı |
| M-02 | $fillable ZORUNLU | Mass assignment koruması |
| M-03 | $casts ZORUNLU | Enum, date, JSON alanları |
| M-04 | Relations tanımlanmalı | Lazy loading yerine eager loading tercih |

### 2.5 Error Handling Kuralları

| # | Kural | Açıklama |
|---|-------|----------|
| E-01 | try-catch ile hata yutma YASAK | Exception'lar anlamlı şekilde handle edilmeli |
| E-02 | Custom Exception ZORUNLU | Business rule ihlalleri için domain exception'lar |
| E-03 | Consistent error format | Tüm API hataları aynı JSON yapısında |

### 2.6 Test Kuralları

| # | Kural | Açıklama |
|---|-------|----------|
| T-01 | TDD yaklaşımı | Önce test, sonra kod |
| T-02 | Feature test ZORUNLU | Her endpoint için en az 1 test |
| T-03 | Factory kullanımı | Hard-coded test verisi YASAK |
| T-04 | RefreshDatabase | Her test izole |

---

## 3. Yasaklar Listesi (NEVER)

```
❌ Controller'da Model::create(), Model::update(), Model::delete()
❌ Controller'da DB::query(), DB::transaction()
❌ Controller'da business logic (if/else iş kuralı)
❌ Livewire component'te direkt Model değişikliği
❌ Livewire component'te Event dispatch
❌ Action'da DB::transaction() (Service yapar)
❌ Action'da Event dispatch (Service yapar)
❌ Controller'da inline validation ($request->validate([...]))
❌ Hard-coded user ID, role ID, project ID
❌ try-catch ile exception yutma (silent fail)
❌ dd(), dump(), var_dump() production kodda
❌ N+1 query (eager loading kullan)
❌ Raw SQL (Eloquent/Query Builder kullan)
❌ env() helper service kodunda (config() kullan)
❌ Layer skipping (Controller → Action, Controller → Model)
```

---

## 4. Kodlama Konvansiyonları

### 4.1 PHP / Laravel

| Konu | Standart |
|------|----------|
| PHP version | 8.3+ (typed properties, enums, match) |
| Code style | Laravel Pint (PSR-12 bazlı) |
| Strict types | `declare(strict_types=1);` her dosyada |
| Return types | Her metotta belirtilmeli |
| Type hints | Constructor promotion + Union types |
| Null handling | Nullable type `?Type` veya `??` operator |

### 4.2 Constructor Injection

```php
// ✅ Doğru — Constructor promotion
class SprintService
{
    public function __construct(
        private readonly StartSprintAction $startAction,
        private readonly CloseSprintAction $closeAction,
    ) {}
}

// ❌ Yanlış — app() helper, new keyword
class SprintService
{
    public function start(Sprint $sprint)
    {
        $action = app(StartSprintAction::class); // ❌
        $action = new StartSprintAction();        // ❌
    }
}
```

### 4.3 Enum Kullanımı

```php
// ✅ Doğru — Backed enum
enum ProjectRole: string
{
    case Owner     = 'owner';
    case Moderator = 'moderator';
    case Member    = 'member';

    public function rank(): int
    {
        return match ($this) {
            self::Owner     => 3,
            self::Moderator => 2,
            self::Member    => 1,
        };
    }
}

// ❌ Yanlış — String constant
const ROLE_OWNER = 'owner'; // ❌
```

### 4.4 Return Consistency

```php
// API Controller — Her zaman Resource
return new UserStoryResource($story);           // tek kayıt
return UserStoryResource::collection($stories); // liste

// Hata — Her zaman JSON
return response()->json(['message' => '...'], 422);
```

---

## 5. Query Scope Kuralları

### 5.1 Ne Zaman Scope Yazılır?

```
Kural: Aynı WHERE koşulu 2+ yerde kullanılıyorsa → Scope ZORUNLU
```

### 5.2 Örnekler

```php
// ✅ Scope tanımı
class Sprint extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SprintStatus::Active);
    }

    public function scopeForProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', SprintStatus::Closed);
    }
}

// ✅ Kullanım
Sprint::forProject($project->id)->active()->first();
Sprint::forProject($project->id)->closed()->orderByDesc('end_date')->take(5)->get();

// ❌ Yanlış — inline tekrar
Sprint::where('project_id', $projectId)->where('status', 'active')->first();
Sprint::where('project_id', $projectId)->where('status', 'active')->exists();
```

---

## 6. Eager Loading Kuralları

```php
// ✅ Controller/Service'te eager loading
$stories = UserStory::with(['epic', 'assignedTo', 'storyPoints'])
    ->forProject($projectId)
    ->get();

// ❌ N+1 problemi
$stories = UserStory::forProject($projectId)->get();
foreach ($stories as $story) {
    echo $story->epic->title;       // ❌ her story için query
    echo $story->assignedTo->name;  // ❌ her story için query
}
```

### preventLazyLoading (Development)

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}
```

---

## 7. Git Kuralları

### 7.1 Commit Message Format

```
type(scope): description

Örnekler:
feat(sprint): implement sprint start/close workflow
fix(rbac): fix owner transfer policy check
test(story): add status transition unit tests
refactor(action): extract scope change detection
docs(readme): update installation instructions
```

**Types:** `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `style`

### 7.2 Branch Strategy

```
main              ← production-ready
  └── develop     ← aktif geliştirme
       ├── feature/sprint-workflow
       ├── feature/issue-tracker
       ├── fix/rbac-member-check
       └── test/burndown-calculation
```

---

## 8. Code Review Checklist

Her PR'da aşağıdaki kontroller yapılır:

### 8.1 Katman İhlali Kontrolü

- [ ] Controller'da `Model::create/update/delete` var mı? → **RED FLAG**
- [ ] Controller'da `DB::` çağrısı var mı? → **RED FLAG**
- [ ] Controller'da if/else business logic var mı? → **RED FLAG**
- [ ] Livewire component'te direkt model değişikliği var mı? → **RED FLAG**
- [ ] Action'da `DB::transaction()` var mı? → **RED FLAG**
- [ ] Action'da Event dispatch var mı? → **RED FLAG**
- [ ] Controller → Action direkt çağrı var mı? → **RED FLAG (layer skip)**

### 8.2 Güvenlik Kontrolü

- [ ] FormRequest tanımlı mı?
- [ ] Policy tanımlı mı?
- [ ] Hard-coded ID var mı?
- [ ] Kullanıcı girdisi sanitize ediliyor mu?
- [ ] Mass assignment koruması ($fillable) var mı?

### 8.3 Kalite Kontrolü

- [ ] Test yazılmış mı? (TDD)
- [ ] Query scope kullanılmış mı?
- [ ] Eager loading uygulanmış mı?
- [ ] Return type belirtilmiş mi?
- [ ] `declare(strict_types=1)` var mı?
- [ ] Exception anlamlı mı (try-catch yutma yok)?

### 8.4 Performans Kontrolü

- [ ] N+1 query riski var mı?
- [ ] Gereksiz `->get()` çağrısı var mı (paginate kullan)?
- [ ] Büyük koleksiyon işlemi var mı (chunk/cursor kullan)?

---

## 9. Laravel Pint Konfigürasyonu

```json
// pint.json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "final_class": false,
        "no_unused_imports": true,
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "trailing_comma_in_multiline": true,
        "single_trait_insert_per_statement": true
    }
}
```

Çalıştırma:

```bash
# Format kontrol
./vendor/bin/pint --test

# Otomatik düzeltme
./vendor/bin/pint
```

---

## 10. Dosya Başlığı Şablonu

```php
<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Enums\SprintStatus;
use App\Exceptions\ActiveSprintAlreadyExistsException;
use App\Models\Sprint;

/**
 * Tek bir sprint'i başlatır.
 * 
 * İş kuralı: Aynı projede aynı anda sadece 1 aktif sprint olabilir (BR-08).
 */
class StartSprintAction
{
    public function execute(Sprint $sprint): Sprint
    {
        // ...
    }
}
```

---

## 11. Özet: Doğru vs Yanlış

| Durum | ✅ Doğru | ❌ Yanlış |
|-------|---------|----------|
| Veri oluşturma | Service → Action → Model | Controller → Model::create() |
| Validation | FormRequest class | `$request->validate([...])` |
| Authorization | Policy + Middleware | `if ($user->role === 'admin')` |
| Transaction | Service katmanında | Action veya Controller'da |
| Event dispatch | Service katmanında | Action veya Livewire'da |
| Tekrar eden query | Query Scope | Inline `where()` tekrarı |
| Test verisi | Factory + Faker | Hard-coded değerler |
| Error handling | Custom Exception | `try { } catch { return null; }` |
| Config erişimi | `config('app.key')` | `env('APP_KEY')` |
| Dependency | Constructor injection | `app()` helper veya `new` |

---

**Önceki:** [13-TESTING_STRATEGY.md](./13-TESTING_STRATEGY.md)
