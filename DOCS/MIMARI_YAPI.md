# Canopy - Mimari Yapı (Detaylı Rehber)

---

## İçindekiler

1. [Mimari Nedir ve Neden Önemlidir?](#1-mimari-nedir-ve-neden-önemlidir)
2. [Genel Bakış: Katmanlı Mimari](#2-genel-bakış-katmanlı-mimari)
3. [Klasör Yapısı](#3-klasör-yapısı)
4. [Request Yaşam Döngüsü (Bir İsteğin Yolculuğu)](#4-request-yaşam-döngüsü-bir-i̇steğin-yolculuğu)
5. [Katman 1: Routes (Yönlendirme)](#5-katman-1-routes-yönlendirme)
6. [Katman 2: Middleware (Ara Katman)](#6-katman-2-middleware-ara-katman)
7. [Katman 3: Form Requests (Doğrulama)](#7-katman-3-form-requests-doğrulama)
8. [Katman 4: Controllers (Kontrolcüler)](#8-katman-4-controllers-kontrolcüler)
9. [Katman 5: Policies (Yetkilendirme)](#9-katman-5-policies-yetkilendirme)
10. [Katman 6: Services (Servis Katmanı)](#10-katman-6-services-servis-katmanı)
11. [Katman 7: Actions (İş Mantığı)](#11-katman-7-actions-i̇ş-mantığı)
12. [Katman 8: Models & Eloquent (Veri Katmanı)](#12-katman-8-models--eloquent-veri-katmanı)
13. [Katman 9: Events & Listeners (Olay Sistemi)](#13-katman-9-events--listeners-olay-sistemi)
14. [Katman 10: Resources (API Dönüşüm Katmanı)](#14-katman-10-resources-api-dönüşüm-katmanı)
15. [Enums (Sabit Değerler)](#15-enums-sabit-değerler)
16. [Traits (Yeniden Kullanılabilir Davranışlar)](#16-traits-yeniden-kullanılabilir-davranışlar)
17. [Exceptions (Özel Hata Sınıfları)](#17-exceptions-özel-hata-sınıfları)
18. [State Machine (Durum Makinesi)](#18-state-machine-durum-makinesi)
19. [RBAC — Rol Tabanlı Erişim Kontrolü](#19-rbac--rol-tabanlı-erişim-kontrolü)
20. [Frontend Mimarisi: Livewire + Flux UI](#20-frontend-mimarisi-livewire--flux-ui)
21. [Gerçek Zamanlı İletişim: Laravel Reverb](#21-gerçek-zamanlı-i̇letişim-laravel-reverb)
22. [Kimlik Doğrulama: Sanctum](#22-kimlik-doğrulama-sanctum)
23. [Dosya Yönetimi: S3](#23-dosya-yönetimi-s3)
24. [İş Kuralları ve Kısıtlamalar](#24-i̇ş-kuralları-ve-kısıtlamalar)
25. [Test Stratejisi](#25-test-stratejisi)
26. [Veri Akış Diyagramları](#26-veri-akış-diyagramları)

---

## 2. Genel Bakış: Katmanlı Mimari

Canopy, **katmanlı mimari** (Layered Architecture) kullanır. Her katmanın belirli bir sorumluluğu vardır ve veriler katmanlar arasında yukarıdan aşağıya akar:

```
┌─────────────────────────────────────────────────────────┐
│                      CLIENT (Tarayıcı)                  │
│            Livewire UI  /  API İstemcisi                │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTP Request
                       ▼
┌──────────────────────────────────────────────────────────┐
│  ROUTES  (web.php / api.php)                             │
│  → İstek nereye gidecek?                                 │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  MIDDLEWARE  (EnsureProjectMember, EnsureProjectRole)    │
│  → Kullanıcı bu işleme yetkili mi?                       │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  FORM REQUEST  (CreateIssueRequest, CreateSprintRequest) │
│  → Gelen veri doğru formatta mı?                         │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  CONTROLLER  (IssueController, SprintController)         │
│  → İsteği al, ilgili servise yönlendir, yanıt dön        │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  POLICY  (IssuePolicy, ProjectPolicy)                    │
│  → Bu kullanıcı bu kaynağı değiştirebilir mi?            │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  SERVICE  (IssueService, SprintService)                  │
│  → İş akışını koordine et (transaction, event dispatch)  │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  ACTION  (CreateIssueAction, StartSprintAction)          │
│  → Tek bir iş kuralını uygula (saf mantık)               │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  MODEL / ELOQUENT  (Issue, Sprint, User)                 │
│  → Veritabanı ile konuş                                  │
└──────────────────────┬───────────────────────────────────┘
                       ▼
┌──────────────────────────────────────────────────────────┐
│  EVENT → LISTENER                                        │
│  → Yan etkiler: bildirim gönder, burndown güncelle, log  │
└──────────────────────────────────────────────────────────┘
                       ▲
                       │ Broadcast (WebSocket)
┌──────────────────────┴───────────────────────────────────┐
│  RESOURCE  (IssueResource, SprintResource)               │
│  → Veriyi API yanıtı formatına dönüştür (JSON)           │
└──────────────────────────────────────────────────────────┘
```

---

## 3. Klasör Yapısı

```
app/
├── Actions/                 # İş mantığı (her dosya tek bir işlem yapar)
│   ├── Analytics/           # Burndown, Velocity hesaplamaları
│   ├── Auth/                # Giriş, kayıt işlemleri
│   ├── File/                # Dosya yükleme/silme
│   ├── Issue/               # Sorun oluşturma, durum değiştirme
│   ├── Notification/        # Bildirim gönderme/okuma
│   ├── Project/             # Proje + üyelik işlemleri
│   └── Scrum/               # Sprint, story, task, epic işlemleri
│
├── Enums/                   # Sabit değerler (durum, rol, tip, öncelik)
│
├── Events/                  # Olaylar (broadcast edilecek veriler)
│   ├── Issue/               # IssueCreated, IssueStatusChanged
│   ├── Notification/        # NotificationSent
│   ├── Project/             # MemberAdded, MemberRemoved, ProjectCreated
│   └── Scrum/               # Sprint*, Story*, Task* olayları
│
├── Exceptions/              # Özel hata sınıfları
│
├── Http/
│   ├── Controllers/         # İstekleri karşılayan kontrolcüler
│   │   ├── Analytics/
│   │   ├── Auth/
│   │   ├── Issue/
│   │   ├── Project/
│   │   └── Scrum/
│   ├── Middleware/           # Ara katman filtreleri
│   ├── Requests/            # Giriş doğrulama kuralları
│   └── Resources/           # JSON dönüşüm formatları
│
├── Listeners/               # Olay dinleyiciler (yan etkiler)
│
├── Models/                  # Eloquent modelleri (veritabanı temsili)
│
├── Policies/                # Yetkilendirme kuralları
│
├── Services/                # İş akışı koordinasyonu
│
├── Traits/                  # Yeniden kullanılabilir davranışlar
│
└── Providers/               # Uygulama servis sağlayıcıları

routes/
├── web.php                  # Livewire (Web) rotaları
├── api.php                  # REST API rotaları
├── channels.php             # WebSocket kanal yetkilendirmeleri
└── console.php              # Artisan komut tanımları

resources/
├── views/                   # Blade / Livewire şablonları
├── js/                      # JavaScript (Echo, Reverb bağlantısı)
└── css/                     # Tailwind CSS stilleri

config/
├── broadcasting.php         # Reverb yapılandırması
├── reverb.php               # Reverb sunucu ayarları
├── sanctum.php              # API token ayarları
└── ...                      # Diğer Laravel ayarları
```

---

## 4. Request Yaşam Döngüsü (Bir İsteğin Yolculuğu)

Bir kullanıcı "Yeni Issue Oluştur" butonuna bastığında ne olur? Adım adım takip edelim:

```
1. Kullanıcı formu doldurur ve gönderir
   POST /api/projects/my-project/issues
   Body: { "title": "Login sayfası çalışmıyor", "type": "bug", ... }

2. ROUTES (api.php)
   → Route::apiResource('issues', IssueController::class)
   → IssueController@store metodu eşleşir

3. MIDDLEWARE
   → auth:sanctum → Token geçerli mi? ✓
   → project.member → Kullanıcı bu projenin üyesi mi? ✓

4. FORM REQUEST (CreateIssueRequest)
   → title: required, string, max 255 ✓
   → type: required, must be bug|question|enhancement ✓
   → authorize(): IssuePolicy::create() kontrolü ✓

5. CONTROLLER (IssueController@store)
   → Form Request doğrulandı, servisi çağır
   → $this->issueService->create($data, $project, $user)

6. SERVICE (IssueService::create)
   → DB::transaction başlat (hata olursa geri al)
   → Action'ı çağır: CreateIssueAction::execute()
   → Event dispatch: IssueCreated::dispatch()
   → Transaction commit

7. ACTION (CreateIssueAction::execute)
   → Varsayılan değerleri ata (priority: normal, severity: minor)
   → $project->issues()->create($data)
   → Yeni Issue modeli döndür

8. EVENT → LISTENER
   → IssueCreated event'i tetiklenir
   → ShouldBroadcast: WebSocket üzerinden broadcast edilir
   → Listener'lar çalışır (bildirim, log)

9. RESOURCE (IssueResource)
   → Issue modelini JSON formatına dönüştür
   → Gereksiz alan gizle, ilişkileri format

10. RESPONSE
    → HTTP 201 Created
    → { "data": { "id": "uuid", "title": "...", ... } }
```

---

## 5. Katman 1: Routes (Yönlendirme)

### Web Routes (`routes/web.php`)

Livewire bileşenlerini sunan rotalar:

```php
// Misafir (giriş yapmamış) kullanıcılar
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login');
    Route::livewire('/register', 'auth.register');
});

// Giriş yapmış kullanıcılar
Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'dashboard');

    // Proje içi rotalar
    Route::prefix('/projects/{project:slug}')->group(function () {
        Route::livewire('/', 'projects.project-dashboard');
        Route::livewire('/backlog', 'scrum.backlog');
        Route::livewire('/board', 'scrum.kanban-board');
        // ...
    });
});
```

**`Route::livewire()`:** URL'yi doğrudan bir Livewire bileşenine bağlar. Geleneksel controller kullanmaz.

**`{project:slug}`:** Route model binding — URL'deki slug otomatik olarak Project modeline dönüşür.

### API Routes (`routes/api.php`)

RESTful API endpoint'leri:

```php
Route::middleware('auth:sanctum')->group(function () {
    // Resource controller: index, store, show, update, destroy
    Route::apiResource('projects', ProjectController::class);

    // Proje kapsamındaki rotalar
    Route::prefix('projects/{project:slug}')
        ->middleware('project.member')
        ->group(function () {
            Route::apiResource('issues', IssueController::class);
            Route::put('issues/{issue}/status', [IssueController::class, 'changeStatus']);
            // ...
        });
});
```

**`apiResource`:** Otomatik olarak 5 rota oluşturur: `index`, `store`, `show`, `update`, `destroy`.

---

## 6. Katman 2: Middleware (Ara Katman)

Middleware, her isteğin controller'a ulaşmadan önce geçmesi gereken "filtre"lerdir.

### `EnsureProjectMember`

```php
// Kullanıcı bu projenin üyesi mi kontrol eder
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    $project = $request->route('project');

    // Süper admin her yere erişir
    if ($user->isSuperAdmin()) {
        return $next($request);
    }

    // Üyelik kontrolü
    $membership = ProjectMembership::where('project_id', $project->id)
        ->where('user_id', $user->id)
        ->first();

    if (! $membership) {
        abort(403, 'Bu projenin üyesi değilsiniz.');
    }

    // Üyeliği request'e ekle (tekrar sorgulanmasın)
    $request->attributes->set('membership', $membership);

    return $next($request);
}
```

### `EnsureProjectRole`

```php
// Minimum rol kontrolü (ör: en az moderator olmalı)
// Kullanım: ->middleware('project.role:moderator')
public function handle(Request $request, Closure $next, string $minimumRole): Response
{
    $membership = $request->attributes->get('membership');
    $requiredRole = ProjectRole::from($minimumRole);

    if (! $membership->isAtLeast($requiredRole)) {
        abort(403, "Bu işlem için en az {$requiredRole->label()} olmalısınız.");
    }

    return $next($request);
}
```

**Middleware zinciri:**
```
auth:sanctum → project.member → project.role:moderator → Controller
```

---

## 7. Katman 3: Form Requests (Doğrulama)

Kullanıcıdan gelen verileri **doğrulayan** sınıflardır. Controller'ı kirletmeden doğrulama mantığını ayırır.

### Örnek: `CreateSprintRequest`

```php
class CreateSprintRequest extends FormRequest
{
    // Yetkilendirme: Policy kontrolü
    public function authorize(): bool
    {
        $project = $this->route('project');
        return $this->user()->can('create', [Sprint::class, $project]);
    }

    // Doğrulama kuralları
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],  // BR-07
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    // Ekstra doğrulama: Sprint süresi 1-30 gün arası olmalı
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->input('start_date');
            $end = $this->input('end_date');
            if ($start && $end) {
                $days = Carbon::parse($start)->diffInDays(Carbon::parse($end));
                if ($days < 1 || $days > 30) {
                    $validator->errors()->add('end_date', 'Sprint süresi 1-30 gün olmalıdır.');
                }
            }
        });
    }
}
```

**`authorize()`:** Bu metot `false` döndürürse istek 403 Forbidden ile reddedilir. İçinde Policy kontrolleri yapılır.

**`rules()`:** Laravel'in built-in doğrulama kuralları. Geçemeyen veriler 422 Unprocessable Entity ile döner.

---

## 8. Katman 4: Controllers (Kontrolcüler)

Controller'lar **ince** tutulur. İş mantığı içermezler — sadece:
1. İsteği alır
2. İlgili servisi çağırır
3. Yanıtı Resource olarak döndürür

### Örnek: `IssueController`

```php
class IssueController extends Controller
{
    public function __construct(
        private IssueService $issueService,   // Dependency Injection
    ) {}

    public function store(CreateIssueRequest $request, Project $project): IssueResource
    {
        // Form Request zaten doğrulama ve yetkilendirmeyi yaptı
        $issue = $this->issueService->create(
            $request->validated(),    // Sadece doğrulanmış veriler
            $project,
            $request->user()
        );

        return new IssueResource($issue->load('creator'));
    }

    public function changeStatus(ChangeStatusRequest $request, Project $project, Issue $issue): IssueResource
    {
        $this->authorize('changeStatus', $issue);  // Policy kontrolü

        $newStatus = IssueStatus::from($request->validated()['status']);
        $issue = $this->issueService->changeStatus($issue, $newStatus, $request->user());

        return new IssueResource($issue);
    }
}
```

**Dependency Injection:** `__construct()` içinde servisi enjekte ediyoruz. Laravel'in container'ı otomatik olarak doğru sınıfı oluşturup verir.

---

## 9. Katman 5: Policies (Yetkilendirme)

Policy'ler, "**bu kullanıcı bu kaynağı değiştirebilir mi?**" sorusunu yanıtlar.

### Rol Hiyerarşisi

```
Super Admin → Her şeye erişir (before() ile bypass)
     ↓
Owner (rank: 3) → Proje ayarları, silme, transfer, rol değiştirme
     ↓
Moderator (rank: 2) → Epic, Story, Sprint CRUD; Issue düzenleme
     ↓
Member (rank: 1) → Temel görüntüleme, kendi issue'larını düzenleme, görev oluşturma
```

### Örnek: `ProjectPolicy`

```php
class ProjectPolicy
{
    // Super admin tüm kontrolleri atlar
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        return null;  // Normal kontrol devam etsin
    }

    // P1: Ayar güncelleme → Sadece Owner
    public function update(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project)?->isAtLeast(ProjectRole::Owner) ?? false;
    }

    // P4: Üye ekleme → Owner + Moderator
    public function addMember(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project)?->isAtLeast(ProjectRole::Moderator) ?? false;
    }

    // P29: Projeyi görüntüleme → Tüm üyeler
    public function view(User $user, Project $project): bool
    {
        return $user->isMemberOf($project);
    }

    // Yardımcı: Kullanıcının proje rolünü getir
    private function getMemberRole(User $user, Project $project): ?ProjectRole
    {
        return $user->projectMemberships()
            ->where('project_id', $project->id)
            ->first()
            ?->role;
    }
}
```

### Yetki Tablosu (Tüm Modeller)

| İzin | Owner | Moderator | Member |
|------|-------|-----------|--------|
| Proje ayarları güncelle | ✅ | ❌ | ❌ |
| Proje sil | ✅ | ❌ | ❌ |
| Sahiplik devret | ✅ | ❌ | ❌ |
| Üye ekle/çıkar | ✅ | ✅ | ❌ |
| Rol değiştir | ✅ | ❌ | ❌ |
| Epic CRUD | ✅ | ✅ | ❌ |
| Story CRUD | ✅ | ✅ | ❌ |
| Sprint CRUD | ✅ | ✅ | ❌ |
| Sprint başlat/kapat | ✅ | ✅ | ❌ |
| Story tahmini | ✅ | ✅ | ❌ |
| Sprint'e taşı | ✅ | ✅ | ❌ |
| Task oluştur | ✅ | ✅ | ✅ |
| Task ata | ✅ | ✅ | ❌ |
| Task düzenle | ✅ | ✅ | Kendi oluşturduğu |
| Issue oluştur | ✅ | ✅ | ✅ |
| Issue düzenle | ✅ | ✅ | Kendi oluşturduğu |
| Issue sil | ✅ | ✅ | Kendi oluşturduğu |
| Projeyi görüntüle | ✅ | ✅ | ✅ |

---

## 10. Katman 6: Services (Servis Katmanı)

Service katmanı, **iş akışını koordine eder**. Tek başına iş kuralı içermez ama:
- Transaction'ları yönetir
- Action'ları çağırır
- Event'leri dispatch eder
- Hata yönetimi yapar

### Örnek: `SprintService`

```php
class SprintService
{
    public function __construct(
        private StartSprintAction $startAction,
        private CloseSprintAction $closeAction,
    ) {}

    public function start(Sprint $sprint, User $user): Sprint
    {
        // 1. Transaction içinde Action'ı çağır
        $sprint = DB::transaction(function () use ($sprint) {
            return $this->startAction->execute($sprint);
        });

        // 2. Broadcast event dispatch et (hata olsa bile sprint başlamış olur)
        try {
            SprintStarted::dispatch($sprint, $user);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for SprintStarted', ['error' => $e->getMessage()]);
        }

        return $sprint;
    }
}
```

**Neden Service ayrı, Action ayrı?**
- **Action:** Tek bir iş kuralı uygular (ör: sprint'i aktif yap, başka aktif sprint var mı kontrol et)
- **Service:** Birden fazla Action'ı ve yan etkiyi (event, transaction) koordine eder

### Tüm Servisler ve Sorumlulukları

| Servis | Sorumluluk |
|--------|-----------|
| `AuthService` | Kayıt, giriş, çıkış işlemleri |
| `ProjectService` | Proje oluşturma (otomatik owner ekleme), güncelleme, silme |
| `MembershipService` | Üye ekleme/çıkarma, rol değiştirme, sahiplik devretme |
| `SprintService` | Sprint CRUD, başlatma, kapatma |
| `UserStoryService` | Hikaye CRUD, durum değiştirme, sprint'e taşıma, tahmin, sıralama |
| `TaskService` | Görev CRUD, durum değiştirme, atama |
| `EpicService` | Epic CRUD |
| `IssueService` | Sorun CRUD, durum değiştirme |
| `NotificationService` | Bildirim gönderme, okuma işaretleme |
| `AttachmentService` | Dosya yükleme/silme (S3) |
| `BurndownService` | Burndown chart verisi hesaplama |
| `VelocityService` | Takım hız verisi hesaplama |

---

## 11. Katman 7: Actions (İş Mantığı)

Action'lar, **tek bir iş kuralını** uygulayan sınıflardır. Her Action'ın tek bir `execute()` metodu vardır.

### Örnek: `StartSprintAction`

```php
class StartSprintAction
{
    public function execute(Sprint $sprint): Sprint
    {
        // BR-05: Projede aynı anda sadece 1 aktif sprint olabilir
        $hasActiveSprint = Sprint::where('project_id', $sprint->project_id)
            ->where('status', SprintStatus::Active)
            ->exists();

        if ($hasActiveSprint) {
            throw new ActiveSprintAlreadyExistsException($sprint->project_id);
        }

        // State machine ile geçiş yap
        $sprint->transitionTo(SprintStatus::Active->value);

        return $sprint;
    }
}
```

### Örnek: `AddMemberAction`

```php
class AddMemberAction
{
    public function execute(Project $project, User $user, ProjectRole $role): ProjectMembership
    {
        // BR-11: Maksimum 5 üye
        if ($project->memberships()->count() >= 5) {
            throw new MaxMembersExceededException($project->id);
        }

        // BR-12: Aynı kullanıcı iki kez eklenemez
        if ($user->isMemberOf($project)) {
            throw new DuplicateMemberException($user->id, $project->id);
        }

        return $project->memberships()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
```

### Tüm Action'lar

| Dizin | Action | İş Kuralı |
|-------|--------|-----------|
| Analytics | `CalculateBurndownAction` | Sprint burndown chart verisi hesapla |
| Analytics | `CalculateVelocityAction` | Takım hız verisi hesapla |
| Analytics | `SnapshotDailyBurndownAction` | Günlük burndown snapshot'ı al |
| Auth | `AuthenticateUserAction` | Email/şifre ile kimlik doğrula |
| Auth | `CreateUserAction` | Yeni kullanıcı oluştur |
| File | `UploadFileAction` | S3'e dosya yükle, Attachment kaydı oluştur |
| File | `DeleteFileAction` | S3'ten dosya sil, Attachment kaydını kaldır |
| Issue | `CreateIssueAction` | BR-18: Varsayılan öncelik/ciddiyet ata, issue oluştur |
| Issue | `ChangeIssueStatusAction` | State machine ile durum geçişi |
| Notification | `SendNotificationAction` | Bildirim oluştur, broadcast et |
| Notification | `MarkAsReadAction` | Bildirimi okundu işaretle |
| Project | `CreateProjectAction` | Proje oluştur, slug üret, ayarları başlat |
| Project | `AddMemberAction` | BR-11: Max 5 üye, BR-12: Tekil üyelik |
| Project | `RemoveMemberAction` | BR-14: Owner çıkarılamaz |
| Project | `TransferOwnershipAction` | Sahiplik devret, roller güncelle |
| Scrum | `CreateUserStoryAction` | BR-01: Yeni = backlog, BR-02: Sona ekle |
| Scrum | `ChangeStoryStatusAction` | State machine ile durum geçişi |
| Scrum | `MoveStoryToSprintAction` | Sprint'e taşı, kapsam değişikliği tespit et |
| Scrum | `DetectScopeChangeAction` | BR-09: Aktif sprint kapsam değişikliği kaydet |
| Scrum | `CalculateStoryPointsAction` | Rol bazlı puan hesapla, toplam güncelle |
| Scrum | `ReorderBacklogAction` | Backlog sıralama güncelle |
| Scrum | `CreateEpicAction` | Epic oluştur, varsayılan renk ata |
| Scrum | `CalculateEpicCompletionAction` | Epic tamamlanma yüzdesi hesapla |
| Scrum | `StartSprintAction` | BR-05: Tek aktif sprint, state machine geçişi |
| Scrum | `CloseSprintAction` | BR-08: Sprint kapat, bitmemiş hikayeleri backlog'a al |
| Scrum | `ChangeTaskStatusAction` | BR-16: Atanmamış görev başlatılamaz |

---

## 12. Katman 8: Models & Eloquent (Veri Katmanı)

Modeller veritabanı tablolarını PHP nesneleri olarak temsil eder. Bu katman hakkında detaylı bilgi için **VERITABANI_ILISKILERI.md** dökümanına bakınız.

### Önemli Model Özellikleri

**UUID Kullanımı:**
```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Project extends Model { use HasUuids; }
```

**Casts (Otomatik Tip Dönüşümü):**
```php
protected function casts(): array
{
    return [
        'status' => SprintStatus::class,   // String → Enum
        'settings' => 'json',               // JSON string → PHP array
        'start_date' => 'date',             // String → Carbon
    ];
}
```

**Scopes (Hazır Filtreler):**
```php
// Model tanımı:
public function scopeActive($query) {
    return $query->where('status', SprintStatus::Active);
}

// Kullanım:
Sprint::active()->get();  // WHERE status = 'active'
```

---

## 13. Katman 9: Events & Listeners (Olay Sistemi)

### Event (Olay) Nedir?
Bir şey olduğunda "bağıran" sınıf. "Hey, bir issue oluşturuldu!" der.

### Listener (Dinleyici) Nedir?
Event'i "duyan" ve bir işlem yapan sınıf. "Issue oluşturuldu? O zaman bildirim göndereyim."

### Neden Event/Listener Kullanıyoruz?
**Loose coupling** (gevşek bağlantı): Issue oluşturma kodu bildirim kodundan habersiz. Yeni bir yan etki eklemek istediğimizde sadece yeni bir Listener yazarız.

### Event → Listener Eşlemeleri

Bu eşlemeler `AppServiceProvider`'da tanımlıdır:

```php
// AppServiceProvider::boot()

Event::listen(StoryStatusChanged::class, [
    RecalculateEpicCompletion::class,      // Epic yüzdesini güncelle
    SendStatusChangeNotification::class,    // Bildirim gönder
    UpdateBurndownSnapshot::class,          // Burndown verisi güncelle
]);

Event::listen(TaskStatusChanged::class, [
    SendStatusChangeNotification::class,    // Bildirim gönder
]);

Event::listen(TaskAssigned::class, [
    SendTaskAssignedNotification::class,    // Atama bildirimi
]);

Event::listen(MemberAdded::class, [
    SendMemberAddedNotification::class,     // Hoşgeldin bildirimi
]);

Event::listen(SprintClosed::class, [
    ReturnUnfinishedStoriesToBacklog::class, // Bitmemiş hikayeleri geri al
]);
```

### Tüm Event'ler

| Event | Ne Zaman Tetiklenir | Broadcast? |
|-------|---------------------|------------|
| `IssueCreated` | Yeni issue oluşturulduğunda | ✅ project.{id} |
| `IssueStatusChanged` | Issue durumu değiştiğinde | ✅ project.{id} |
| `StoryCreated` | Yeni user story oluşturulduğunda | ✅ project.{id} |
| `StoryStatusChanged` | Story durumu değiştiğinde | ✅ project.{id} |
| `TaskStatusChanged` | Task durumu değiştiğinde | ✅ project.{id} |
| `TaskAssigned` | Bir görev birine atandığında | ✅ user.{id} |
| `SprintStarted` | Sprint başlatıldığında | ✅ project.{id} |
| `SprintClosed` | Sprint kapatıldığında | ✅ project.{id} |
| `MemberAdded` | Projeye üye eklendiğinde | ✅ user.{id} + project.{id} |
| `MemberRemoved` | Projeden üye çıkarıldığında | ❌ |
| `ProjectCreated` | Yeni proje oluşturulduğunda | ❌ |
| `NotificationSent` | Bildirim gönderildiğinde | ✅ user.{id} |

### Tüm Listener'lar ve İşlevleri

| Listener | Dinlediği Event | Ne Yapar |
|----------|----------------|----------|
| `RecalculateEpicCompletion` | StoryStatusChanged | Epic'in tamamlanma yüzdesini günceller |
| `UpdateBurndownSnapshot` | StoryStatusChanged | Burndown chart verisini günceller |
| `SendStatusChangeNotification` | Story/Task/Issue StatusChanged | İlgili kullanıcılara bildirim gönderir |
| `SendTaskAssignedNotification` | TaskAssigned | Task atanan kişiye bildirim gönderir |
| `SendMemberAddedNotification` | MemberAdded | Yeni üyeye hoşgeldin bildirimi gönderir |
| `ReturnUnfinishedStoriesToBacklog` | SprintClosed | Bitmemiş hikayeleri backlog'a döndürür |

---

## 14. Katman 10: Resources (API Dönüşüm Katmanı)

Resource'lar, Eloquent modellerini **API yanıt formatına** dönüştürür.

### Neden Resource Kullanıyoruz?
- Model'deki tüm alanları göstermek istemeyiz (ör: `password`)
- İlişkili verileri tutarlı bir formatta döndürmek isteriz
- Tarih formatlarını standartlaştırırız (ISO8601)

### Örnek: `IssueResource`

```php
class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type?->value,           // Enum → string
            'priority' => $this->priority?->value,
            'severity' => $this->severity?->value,
            'status' => $this->status?->value,
            'creator' => new UserResource($this->whenLoaded('creator')),      // Lazy load
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**`whenLoaded()`:** İlişki eager-load edilmişse dahil et, edilmemişse hiç gösterme. Bu, N+1 sorgu problemini önler.

---

## 15. Enums (Sabit Değerler)

PHP 8.1 Backed Enums kullanılır. Her enum:
- `value` → Veritabanındaki string değer
- `label()` → Türkçe kullanıcı arayüzü etiketi
- `color()` → UI'da kullanılacak renk kodu

### State Machine Destekli Enum'lar

`IssueStatus`, `StoryStatus`, `TaskStatus`, `SprintStatus` enum'ları durum geçiş kurallarını içerir:

```php
enum StoryStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Done = 'done';

    // Hangi durumdan hangi duruma geçilebilir?
    public static function allowedTransitions(): array
    {
        return [
            'new' => ['in_progress'],
            'in_progress' => ['new', 'done'],
            'done' => ['in_progress'],
        ];
    }

    public function canTransitionTo(self $target): bool
    {
        $allowed = self::allowedTransitions()[$this->value] ?? [];
        return in_array($target->value, $allowed, true);
    }
}
```

---

## 16. Traits (Yeniden Kullanılabilir Davranışlar)

### `Auditable`
Modele aktivite log ilişkisi ekler. Kullanıldığı modeller: `Project`, `Epic`, `Sprint`, `UserStory`, `Task`, `Issue`.

### `BelongsToProject`
Modele `project()` ilişkisi ve `scopeForProject()` scope'u ekler. Kullanıldığı modeller: `Epic`, `Sprint`, `UserStory`, `Issue`.

### `HasStateMachine`
Modele durum geçiş kontrolleri ekler: `canTransitionTo()`, `transitionTo()`, `availableTransitions()`. Kullanıldığı modeller: `Sprint`, `UserStory`, `Task`, `Issue`.

---

## 17. Exceptions (Özel Hata Sınıfları)

Her özel hata sınıfı:
- HTTP durum kodu tanımlar
- Türkçe hata mesajı içerir
- JSON yanıt formatı sağlar (`render()` metodu)

| Exception | HTTP Code | Ne Zaman |
|-----------|-----------|----------|
| `ActiveSprintAlreadyExistsException` | 422 | Projede zaten aktif sprint var (BR-05) |
| `DuplicateMemberException` | 422 | Kullanıcı zaten proje üyesi (BR-12) |
| `InvalidStatusTransitionException` | 422 | Geçersiz durum geçişi denendiğinde |
| `MaxMembersExceededException` | 422 | Proje üye limiti aşıldığında (BR-11) |
| `OwnerCannotBeRemovedException` | 403 | Proje sahibi çıkarılmaya çalışıldığında (BR-14) |
| `TaskNotAssignedException` | 422 | Atanmamış görev başlatılmaya çalışıldığında (BR-16) |

---

## 18. State Machine (Durum Makinesi)

### Nedir?
Bir nesnenin hangi durumdan hangi duruma geçebileceğini kontrol eden mekanizma.

### Neden Gerekli?
Bir issue'nun "Done" durumundan doğrudan "New" durumuna geçmesini engellemek gibi iş kurallarını uygulamak için.

### Geçiş Kuralları

**Issue / Story / Task Durumları:**
```
          ┌──────────────┐
          │              │
   ┌──────┤  in_progress ├──────┐
   │      │              │      │
   │      └──────┬───────┘      │
   │             │              │
   ▼             ▼              ▼
┌──────┐   (geri dönüş)   ┌──────┐
│ new  │◄──────────────────│ done │
└──────┘                   └──────┘
   │                          ▲
   └──────── (ileri) ─────────┘
              ❌ (doğrudan geçiş yok!)
```

- `new` → `in_progress` ✅
- `in_progress` → `done` ✅ | `new` ✅
- `done` → `in_progress` ✅
- `new` → `done` ❌ (doğrudan geçilemez!)

**Sprint Durumları:**
```
planning ──→ active ──→ closed
   (tek yönlü, geri dönüş yok!)
```

### Kodda Nasıl Çalışır?

```php
// HasStateMachine trait'i ile:
$sprint->canTransitionTo('active');   // true/false
$sprint->transitionTo('active');       // Geçiş yap veya exception fırlat
$sprint->availableTransitions();       // ['active'] (mümkün geçişler)
```

---

## 19. RBAC — Rol Tabanlı Erişim Kontrolü

### 3 Katmanlı Yetkilendirme Sistemi

```
┌─────────────────────────────────────────┐
│ Katman 1: MIDDLEWARE                     │
│ • auth:sanctum → Giriş yaptı mı?       │
│ • project.member → Üye mi?             │
│ • project.role → Yeterli rolü var mı?  │
└────────────────┬────────────────────────┘
                 ▼
┌─────────────────────────────────────────┐
│ Katman 2: FORM REQUEST authorize()       │
│ • Policy kontrolü                        │
│ • "Bu kullanıcı bu kaynağı              │
│    oluşturabilir/düzenleyebilir mi?"     │
└────────────────┬────────────────────────┘
                 ▼
┌─────────────────────────────────────────┐
│ Katman 3: CONTROLLER $this->authorize()  │
│ • Ek policy kontrolleri                  │
│ • Kaynak bazlı yetkilendirme            │
└─────────────────────────────────────────┘
```

### Super Admin Bypass

```php
// Her Policy'nin before() metodu:
public function before(User $user, string $ability): ?bool
{
    if ($user->isSuperAdmin()) {
        return true;   // Tüm kontrolleri atla
    }
    return null;       // Normal kontrol devam etsin
}
```

---

## 20. Frontend Mimarisi: Livewire + Flux UI

### Livewire Nedir?
JavaScript framework'ü kullanmadan, **PHP ile reaktif UI** oluşturan Laravel paketi. Her bileşen bir PHP sınıfı + Blade şablonu.

### Flux UI Nedir?
Livewire için resmi komponent kütüphanesi. Buton, form, modal gibi hazır UI elemanları sunar.

### Nasıl Çalışır?
```
1. Kullanıcı butona tıklar
2. Livewire AJAX isteği gönderir
3. PHP bileşeni güncellenir
4. Sadece değişen DOM parçaları yeniden render edilir
```

### Rota Yapısı

```php
// Web rotaları doğrudan Livewire bileşenlerine bağlanır:
Route::livewire('/dashboard', 'dashboard');
Route::livewire('/projects/{project:slug}/backlog', 'scrum.backlog');
Route::livewire('/projects/{project:slug}/board', 'scrum.kanban-board');
```

---

## 21. Gerçek Zamanlı İletişim: Laravel Reverb

Reverb, **WebSocket sunucusu** olarak çalışır. Sayfa yenilemeden anlık güncellemeler sağlar.

### Akış

```
PHP Event dispatch → Reverb WebSocket Sunucu → Laravel Echo (JS) → UI Güncelleme
```

### Kanal Tipleri

```php
// Proje kanalı: Tüm proje üyeleri
PrivateChannel('project.{projectId}')

// Kullanıcı kanalı: Sadece o kullanıcı
PrivateChannel('user.{userId}')
```

Detaylı Reverb kullanımı için **LARAVEL_REVERB_KULLANIMI.md** dökümanına bakınız.

---

## 22. Kimlik Doğrulama: Sanctum

### Web (Session-Based)
```
Tarayıcı → Cookie/Session → Laravel Auth
```

### API (Token-Based)
```
Mobil/SPA → Bearer Token → Sanctum → Laravel Auth
```

```php
// API'de giriş sonrası token alma:
$user = AuthService::login($credentials);
$token = $user->createToken('api-token');
// Sonraki isteklerde: Authorization: Bearer {token}
```

---

## 23. Dosya Yönetimi: S3

### Yükleme Akışı

```
Kullanıcı → Dosya Seç → API POST /attachments → UploadFileAction → S3 Upload
                                                                    ↓
                                                            Attachment Kaydı (DB)
```

### Polimorfik Yükleme

Dosyalar 3 farklı modele eklenebilir:
```php
$modelMap = [
    'user_story' => UserStory::class,
    'task' => Task::class,
    'issue' => Issue::class,
];
```

---

## 24. İş Kuralları ve Kısıtlamalar

| Kod | Kural | Uygulayan |
|-----|-------|-----------|
| BR-01 | Yeni hikaye varsayılan `new` durumuyla, backlog'da oluşur | `CreateUserStoryAction` |
| BR-02 | Yeni hikaye backlog'un sonuna eklenir | `CreateUserStoryAction` |
| BR-05 | Projede aynı anda sadece 1 aktif sprint olabilir | `StartSprintAction` |
| BR-07 | Sprint başlangıç tarihi bugün veya sonra olmalı | `CreateSprintRequest` |
| BR-08 | Sprint kapanırken bitmemiş hikayeler backlog'a döner | `CloseSprintAction` + `ReturnUnfinishedStoriesToBacklog` |
| BR-09 | Aktif sprint'te kapsam değişikliği kaydedilir | `DetectScopeChangeAction` |
| BR-11 | Projede maksimum 5 üye olabilir | `AddMemberAction` |
| BR-12 | Aynı kullanıcı bir projeye iki kez eklenemez | `AddMemberAction` |
| BR-13 | Proje oluşturan otomatik owner olur | `ProjectService::create()` |
| BR-14 | Proje sahibi projeden çıkarılamaz | `RemoveMemberAction` |
| BR-15 | Projeler soft delete ile silinir | `Project` model (SoftDeletes) |
| BR-16 | Atanmamış görev `in_progress`'e geçemez | `ChangeTaskStatusAction` |
| BR-18 | Yeni issue varsayılan normal/minor ile oluşur | `CreateIssueAction` |

---

## 25. Test Stratejisi

### Test Türleri

| Tür | Konum | Amaç |
|-----|-------|------|
| Feature Tests | `tests/Feature/` | API endpoint'lerini uçtan uca test et |
| Livewire Tests | `tests/Livewire/` | Livewire bileşenlerini test et |
| Unit Tests | `tests/Unit/` | Action, Service, Model gibi tek birimleri test et |

### Test Araçları

- **PHPUnit:** Ana test framework'ü
- **Factory'ler:** Test verileri oluşturmak için (UserFactory, ProjectFactory, vs.)
- **RefreshDatabase:** Her test sonrası veritabanını sıfırlar

### Test Komutları

```bash
# Tüm testleri çalıştır
php artisan test --compact

# Belirli bir dosyayı test et
php artisan test --compact tests/Feature/IssueTest.php

# Belirli bir test metodunu çalıştır
php artisan test --compact --filter=testCanCreateIssue
```

---

## 26. Veri Akış Diyagramları

### Issue Oluşturma Akışı

```
Client
  │
  │ POST /api/projects/{slug}/issues
  │ { title, type, priority?, severity?, assigned_to? }
  │
  ▼
┌─────────────────┐
│  auth:sanctum   │──── 401 Unauthorized
└────────┬────────┘
         ▼
┌─────────────────┐
│ project.member  │──── 403 Forbidden
└────────┬────────┘
         ▼
┌─────────────────┐
│ CreateIssue     │──── 422 Validation Error
│ Request         │
└────────┬────────┘
         ▼
┌─────────────────┐
│ IssueController │
│ ::store()       │
└────────┬────────┘
         ▼
┌─────────────────┐     ┌──────────────────┐
│ IssueService    │────→│ CreateIssueAction │
│ ::create()      │     │ ::execute()       │
└────────┬────────┘     └──────────────────┘
         │                     │
         │              Veritabanına kaydet
         │
         ▼
┌─────────────────┐     ┌────────────────────┐
│ IssueCreated    │────→│ WebSocket Broadcast│
│ ::dispatch()    │     │ (project.{id})     │
└─────────────────┘     └────────────────────┘
         │
         ▼
┌─────────────────┐
│ IssueResource   │──→ HTTP 201 { data: { ... } }
└─────────────────┘
```

### Sprint Yaşam Döngüsü Akışı

```
[Sprint Oluştur]     [Sprint Başlat]        [Sprint Kapat]
      │                    │                       │
      ▼                    ▼                       ▼
  SprintService       SprintService           SprintService
  ::create()          ::start()               ::close()
      │                    │                       │
      │              StartSprintAction        CloseSprintAction
      │                    │                       │
      │              BR-05: Aktif sprint      BR-08: Durum=Closed
      │              var mı kontrol                │
      │                    │                       ▼
      │              SprintStarted           SprintClosed Event
      │              Event dispatch                │
      │                    │                       ├─→ ReturnUnfinished
      │                    ▼                       │   StoriesToBacklog
      ▼              [Aktif Sprint]                │
  [Planning]         • Hikaye ekle/çıkar           ▼
  • Hikaye ata       • Kapsam değişikliği      [Closed Sprint]
  • Tahmin yap         takip edilir            • Bitmemiş hikayeler
                     • Burndown güncellenir      backlog'a döner
```

### Bildirim Akışı

```
Herhangi bir Event (ör: TaskAssigned)
      │
      ├──→ Listener (SendTaskAssignedNotification)
      │         │
      │         ▼
      │    NotificationService::send()
      │         │
      │         ▼
      │    SendNotificationAction::execute()
      │         │
      │         ├──→ notifications tablosuna kaydet
      │         │
      │         └──→ NotificationSent Event dispatch
      │                    │
      │                    ▼
      │              WebSocket Broadcast
      │              (user.{userId})
      │                    │
      │                    ▼
      │              Client (Echo) alır
      │              → UI'da bildirim gösterir
      │
      └──→ ShouldBroadcast (Event'in kendisi)
                 │
                 ▼
           WebSocket Broadcast
           (project.{projectId})
                 │
                 ▼
           Client (Echo) alır
           → Listeyi günceller (ör: issue listesi)
```

---

## Sonuç: Mimari Özet

```
┌─────────────────────── CANOPY MİMARİSİ ────────────────────────┐
│                                                                  │
│  Frontend: Livewire 4 + Flux UI (Free) + Tailwind CSS v4       │
│  Gerçek Zamanlı: Laravel Reverb + Laravel Echo                  │
│  Kimlik Doğrulama: Laravel Sanctum (Session + Token)            │
│  Dosya Depolama: Amazon S3                                       │
│  Veritabanı: UUID Primary Keys, Soft Delete, Polymorphic       │
│                                                                  │
│  Katmanlar:                                                      │
│  Route → Middleware → FormRequest → Controller → Policy          │
│       → Service → Action → Model → Database                     │
│                                                                  │
│  Yan Etkiler:                                                    │
│  Event → Listener → Notification/Log/Recalculation              │
│       → WebSocket Broadcast                                      │
│                                                                  │
│  Destek Kalıpları:                                               │
│  • Enum State Machine (durum geçiş kontrolü)                    │
│  • RBAC Policies (rol tabanlı yetkilendirme)                    │
│  • Trait-based kod paylaşımı                                     │
│  • Resource dönüşüm katmanı                                     │
│  • Custom Exception sınıfları (Türkçe mesajlar)                 │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```
