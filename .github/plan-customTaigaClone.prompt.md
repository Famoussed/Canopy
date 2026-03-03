# Özelleştirilmiş Taiga Klonu — Mimari & Dokümantasyon Planı (v3)

**Özet:** Laravel 11 + Livewire 3 tabanlı, **Lightweight Clean Architecture** (Service + Action Layered) ile yapılandırılmış proje yönetim platformu. Namespace-based gruplandırma (klasik Laravel yapısı, mantıksal ayrım). Session-based auth, PostgreSQL (prod) + SQLite (dev), Laravel Reverb WebSocket, MinIO dosya depolama, Docker Compose deployment. **TDD yaklaşımıyla** geliştirme. Tüm iş mantığı Service katmanında, Controller ve Livewire component'ler ince tutulacak.

### Mimari Sınıflandırma

> **"Service + Action Layered Architecture with Clean Architecture Principles"**
>
> Clean Architecture'ın **prensiplerini** (katman ayrımı, bağımlılık yönü, iş mantığı izolasyonu) benimseyen, ancak Laravel'in pragmatik yapısını (Eloquent, FormRequest) **framework'e karşı değil framework ile** çalışarak uygulayan katmanlı mimari.

**Neden "Lightweight"?**
- Clean Architecture prensipleri uygulanıyor (katman disiplini, tek yönlü bağımlılık, iş mantığı izolasyonu)
- Ancak strict Clean Architecture'ın getirdiği Repository interfaces, saf PHP domain entities, DTO katmanı gibi ağır soyutlamalar bu ölçek için **overengineering** olduğundan kullanılmıyor
- Model'ler Eloquent'i extend ediyor (framework bağımlılığı kabul ediliyor — pragmatik taviz)

**Neden "Namespace-Based" (Modüler Değil)?**
- Modüller arası bağımlılık çok yüksek (Analytics → Scrum → Project, Notification → hepsi)
- Tek geliştirici / küçük ekip — modül izolasyonu gereksiz overhead
- Monolith deployment — bağımsız deploy ihtiyacı yok
- Migration sıralaması, cross-module FK, shared model erişimi problemlerinden kaçınılıyor
- Laravel'in doğal yapısıyla uyumlu — framework'e karşı değil, framework ile çalışıyoruz

---

## Kritik Mimari Değişiklikler (v1 → v2 → v3)

| Konu | v1 (İlk Plan) | v2 | v3 (Final) |
|------|---------------|----|-----------|
| Mimari Pattern | Action-Based (Controller → Action) | Service + Action | **Lightweight Clean Architecture** (Service + Action Layered) |
| Proje Yapısı | Tam modüler (nwidart tarzı) | Tam modüler | **Namespace-based gruplandırma** (klasik Laravel) |
| Auth | Sanctum JWT | Session-based | **Session-based** + Sanctum SPA mode (cookie) |
| Test | Planlanmamıştı | TDD eklendi | **TDD yaklaşımı**, `13-TESTING_STRATEGY.md` |
| Katman kuralları | Gevşek | Katı | **Katı**: Katman atlama yasak, yasaklar listesi zorunlu |
| Livewire | Belirtilmemişti | Service çağırır | Component'te iş mantığı **yasak**, Service çağırır |

---

## Güncellenmiş Katman Mimarisi

```
HTTP Request / Livewire Action
        ↓
FormRequest (Validation + Authorization)
        ↓
Controller / Livewire Component (İNCE — sadece routing)
        ↓
Service (BEYİN — iş mantığı, orkestrasyon, transaction)
        ↓
Action(s) (İŞÇİ — tek amaçlı, yeniden kullanılır)
        ↓
Model (VERİ — Eloquent, Query Scopes)
        ↓
Domain Events → Listeners (Bildirim, Analytics, Activity Log)
```

### Katı Kurallar

- Controller **SADECE** Service çağırır (Model/Action direkt çağrılamaz)
- Service, Action'ları koordine eder + DB::transaction yönetir
- Action tek iş yapar, birden fazla Service'ten çağrılabilir
- Livewire Component = Controller gibi davranır → **Service çağırır, iş mantığı barındırmaz**
- `Model::create()` Controller/Component'te **YASAK**
- Authorization = **Policy** (manuel ID karşılaştırması yasak)
- Validation = **FormRequest** (inline validation yasak)
- Hard-coded user ID **YASAK**
- Genel try-catch ile hata bastırma **YASAK**

### Katman İletişim Kuralları

| Katman | Amaç | Çağırabilir |
|--------|-------|-------------|
| Controller | HTTP handling | Sadece Service |
| Livewire Component | UI state + routing | Sadece Service |
| Service | İş mantığı orkestrasyon | Action, Model, Policy |
| Action | Tek amaçlı operasyon | Model, diğer Action'lar |
| FormRequest | Validation + Authorization | — |
| Policy | Yetkilendirme | — |
| Resource | API dönüşümü | — |

---

## Temel Etki Alanı Modeli (Core Domain Model)

### Entity Hiyerarşisi

Root (kök) nesne her zaman **Proje (Project)**'dir. Diğer tüm varlıklar projeye bağlıdır.

- **Project (Proje):** Modüllerin (Scrum) aktif veya pasif durumlarını, proje üyelerini ve özel alanları (custom fields) barındırır.
- **Epic (Destan):** Büyük hedefleri temsil eder. Birden fazla User Story'yi gruplar. İş kuralı gereği, bir Epic'in tamamlanma yüzdesi, içindeki User Story'lerin durumuna göre otomatik hesaplanır.
- **User Story (Kullanıcı Hikayesi):** Sistemin en temel değer birimidir. Story Point (Hikaye Puanı) taşır.
- **Task (Görev):** Bir User Story'nin teknik alt kırılımıdır. Kendi durumu vardır ancak puan taşımaz.
- **Issue (Hata/Sorun):** Sistemin hata takip mekanizmasıdır. Tipleri (Bug, Question, Enhancement), Öncelikleri (Low, Normal, High) ve Şiddetleri (Wishlist, Minor, Critical) vardır.

---

## Temel İş Akışları (Business Workflows)

### A. Scrum Modülü Mantığı

1. **Backlog Yönetimi:** Eklenen her yeni User Story varsayılan olarak `New` durumunda Backlog'a düşer.
2. **Puanlama (Estimation):** Story'ler, ekip rolleri bazında (örn. UX, Design, Front, Back) ayrı ayrı puanlanabilir. Toplam puan, bu alt puanların toplamıdır.
3. **Sprint Planlama:** Bir Sprint oluşturulur. Backlog'daki hikayeler kapasite yettiğince (toplam story point) Sprint'e çekilir.
4. **Kural:** Devam eden bir Sprint'e yeni bir hikaye eklenirse, iş mantığı bunu "Sprint Scope Change" (Kapsam Değişikliği) olarak işaretler ve Burndown grafiğine yansıtır.

---

## Durum Makinesi (State Machine)

Her entity için 3 durum: **New → In Progress → Done**

| Geçiş | Koşul | Fırlatılan Event |
|--------|-------|-----------------|
| New → In Progress | Atama yapılmış olmalı (Task) | `StatusChanged` |
| In Progress → Done | — | `StatusChanged`, `CheckEpicCompletion` |
| In Progress → New | Geri alma (rollback) | `StatusChanged` |
| Done → In Progress | Yeniden açma | `StatusReopened` |

### Domain Event Zinciri (Örnek)

1. `UserStoryStatusChanged(story, 'done')` →
2. Listener: Epic tamamlanma yüzdesi yeniden hesapla →
3. Listener: Sprint burndown güncelle →
4. Listener: Atanmış kullanıcılara bildirim gönder →
5. Listener: Activity log oluştur

---

## RBAC Yetki Matrisi (Proje Bazlı)

3 hiyerarşik rol: **Owner > Moderator > Member**

Bir kullanıcı Sistem Yöneticisi (Super User) olmadığı sürece, yalnızca proje içinde atandığı rolün yetkilerini kullanabilir. Aynı kullanıcı farklı projelerde farklı rollere sahip olabilir.

| İzin | Owner | Moderator | Member |
|------|:-----:|:---------:|:------:|
| Proje ayarlarını düzenle | ✅ | ❌ | ❌ |
| Projeyi sil / devret | ✅ | ❌ | ❌ |
| Üye ekle / çıkar | ✅ | ✅ | ❌ |
| Rol değiştir | ✅ | ❌ | ❌ |
| Epic oluştur / düzenle | ✅ | ✅ | ❌ |
| User Story oluştur | ✅ | ✅ | ❌ |
| User Story düzenle | ✅ | ✅ | ❌ |
| Sprint oluştur / yönet | ✅ | ✅ | ❌ |
| Task oluştur / ata | ✅ | ✅ | ❌ |
| Kendi task durumunu değiştir | ✅ | ✅ | ✅ |
| Issue oluştur | ✅ | ✅ | ✅ |
| Issue düzenle (herkesinki) | ✅ | ✅ | ❌ |
| Kendi issue'sunu düzenle | ✅ | ✅ | ✅ |
| Dosya ekle / sil (kendi) | ✅ | ✅ | ✅ |
| Yorum yaz | ✅ | ✅ | ✅ |
| Burndown / rapor görüntüle | ✅ | ✅ | ✅ |

### Auth Middleware Akışı

```
Request → Auth Middleware (Session) → ProjectMember Middleware → Policy (rol kontrolü) → Controller
```

İş Mantığı: Bir endpoint'e istek geldiğinde:
1. Kullanıcının authenticate olup olmadığını kontrol et (Session)
2. Kullanıcının projeye üye olup olmadığını kontrol et (Middleware)
3. İlgili eylemi gerçekleştirmek için rolünde bu bayrağın `true` olup olmadığını kontrol et (Policy)

---

## Metrikler ve Analitik Motoru

### Burndown Chart

- **Girdi:** Seçili Sprint'in toplam günü, Sprint'teki toplam Story Point
- **İdeal Çizgi:** `y = totalPoints - (totalPoints / sprintDays) × dayIndex`
- **Gerçek Çizgi:** Her gün sonu veya durum değişikliğinde → `remainingPoints = totalPoints - Σ(doneStoryPoints)`
- **Scope Change:** Sprint'e eklenen yeni story'lerin puanları `totalPoints`'e eklenir, grafik "basamak" yapar

### Velocity

Son N sprint'teki tamamlanan toplam story point ortalaması:

`velocity = Σ(completedPoints_i, i=1..N) / N`

---

## Teknoloji Yığını

| Katman | Teknoloji | Gerekçe |
|--------|-----------|---------|
| Backend | Laravel 11 | PHP ekosisteminin en olgun framework'ü |
| Frontend | Livewire 3 | SPA deneyimi, ayrı frontend gerekmez, Service çağırımına doğal uyum |
| Veritabanı (Dev) | SQLite | Hızlı geliştirme, sıfır konfigürasyon |
| Veritabanı (Prod) | PostgreSQL 16 | JSONB, concurrent writes, LISTEN/NOTIFY, FTS |
| Cache/Queue/Session | Redis 7 | Performans, broadcast, queue backend |
| Real-time | Laravel Reverb | Native WebSocket, Livewire + Echo entegrasyonu |
| Dosya Depolama | MinIO | Self-hosted S3, Docker Compose uyumlu |
| Auth | Session-based + Sanctum SPA | Livewire doğal uyum, cookie-based API |
| Deployment | Docker Compose | VPS/Dedicated, tek komutla ayağa kalkma |

---

## Veritabanı Şeması (Ana Tablolar)

- **`users`** — id, name, email, password, avatar, is_super_admin, timestamps
- **`sessions`** — id, user_id, ip_address, user_agent, payload, last_activity
- **`projects`** — id, name, slug, description, owner_id(FK→users), settings(JSONB), timestamps, soft_deletes
- **`project_memberships`** — id, project_id, user_id, role(enum: owner/moderator/member), timestamps | UNIQUE(project_id, user_id)
- **`epics`** — id, project_id, title, description, color, status, order, timestamps
- **`user_stories`** — id, project_id, epic_id(nullable), sprint_id(nullable), title, description, status(enum), total_points, custom_fields(JSONB), order, created_by, timestamps
- **`story_points`** — id, user_story_id, role_name(string: UX/Design/Front/Back), points(decimal)
- **`sprints`** — id, project_id, name, start_date, end_date, status(enum: planning/active/closed), timestamps
- **`sprint_scope_changes`** — id, sprint_id, user_story_id, change_type(added/removed), changed_at, changed_by
- **`tasks`** — id, user_story_id, title, description, status(enum), assigned_to(FK→users), created_by, timestamps
- **`issues`** — id, project_id, title, description, type(enum: bug/question/enhancement), priority(enum: low/normal/high), severity(enum: wishlist/minor/critical), status(enum), assigned_to, created_by, timestamps
- **`attachments`** — id, attachable_type, attachable_id (polymorphic), filename, path, mime_type, size, uploaded_by, timestamps
- **`notifications`** — id, user_id, type, data(JSON), read_at, created_at
- **`activity_logs`** — id, project_id, user_id, action, subject_type, subject_id, changes(JSON), created_at

### Index Stratejisi

- `project_memberships`: UNIQUE(project_id, user_id), INDEX(user_id)
- `user_stories`: INDEX(project_id, status), INDEX(sprint_id), INDEX(epic_id)
- `tasks`: INDEX(user_story_id), INDEX(assigned_to, status)
- `issues`: INDEX(project_id, status), INDEX(assigned_to)
- `notifications`: INDEX(user_id, read_at)
- `activity_logs`: INDEX(project_id, created_at)

---

## Proje Yapısı (Namespace-Based Grouping)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                (LoginController, RegisterController)
│   │   ├── Project/             (ProjectController, MembershipController)
│   │   ├── Scrum/               (EpicController, UserStoryController, TaskController, SprintController)
│   │   ├── Issue/               (IssueController)
│   │   └── Analytics/           (AnalyticsController)
│   ├── Requests/
│   │   ├── Auth/                (LoginRequest, RegisterRequest)
│   │   ├── Project/             (CreateProjectRequest, UpdateProjectRequest)
│   │   ├── Scrum/               (CreateUserStoryRequest, PlanSprintRequest, ...)
│   │   └── Issue/               (CreateIssueRequest, UpdateIssueRequest)
│   └── Resources/
│       ├── Project/             (ProjectResource, MemberResource)
│       ├── Scrum/               (EpicResource, UserStoryResource, SprintResource)
│       └── Issue/               (IssueResource)
│
├── Services/                    ← BEYİN: İş mantığı orkestrasyon + transaction
│   ├── AuthService.php
│   ├── ProjectService.php
│   ├── MembershipService.php
│   ├── EpicService.php
│   ├── UserStoryService.php
│   ├── TaskService.php
│   ├── SprintService.php
│   ├── IssueService.php
│   ├── BurndownService.php
│   ├── VelocityService.php
│   ├── NotificationService.php
│   └── AttachmentService.php
│
├── Actions/                     ← İŞÇİ: Tek amaçlı, yeniden kullanılır
│   ├── Auth/                    (CreateUserAction, AuthenticateUserAction)
│   ├── Project/                 (CreateProjectAction, AddMemberAction, RemoveMemberAction)
│   ├── Scrum/                   (CreateUserStoryAction, MoveStoryToSprintAction,
│   │                             CalculateEpicCompletionAction, DetectScopeChangeAction, ...)
│   ├── Issue/                   (CreateIssueAction, ChangeIssueStatusAction)
│   ├── Analytics/               (CalculateBurndownAction, SnapshotDailyBurndownAction)
│   ├── Notification/            (SendNotificationAction, MarkAsReadAction)
│   └── File/                    (UploadFileAction, DeleteFileAction)
│
├── Models/                      ← VERİ: Eloquent, tek yerde — cross-relation kolay
│   ├── User.php
│   ├── Project.php
│   ├── ProjectMembership.php
│   ├── Epic.php
│   ├── UserStory.php
│   ├── StoryPoint.php
│   ├── Sprint.php
│   ├── SprintScopeChange.php
│   ├── Task.php
│   ├── Issue.php
│   ├── Attachment.php
│   ├── Notification.php
│   └── ActivityLog.php
│
├── Enums/                       ← Domain sabit değerleri
│   ├── ProjectRole.php          (Owner, Moderator, Member)
│   ├── StoryStatus.php          (New, InProgress, Done)
│   ├── TaskStatus.php           (New, InProgress, Done)
│   ├── IssueStatus.php          (New, InProgress, Done)
│   ├── SprintStatus.php         (Planning, Active, Closed)
│   ├── IssueType.php            (Bug, Question, Enhancement)
│   ├── IssuePriority.php        (Low, Normal, High)
│   └── IssueSeverity.php        (Wishlist, Minor, Critical)
│
├── Events/                      ← Domain Events
│   ├── Project/                 (ProjectCreated, MemberAdded, MemberRemoved)
│   ├── Scrum/                   (StoryStatusChanged, SprintScopeChanged, TaskAssigned, ...)
│   └── Issue/                   (IssueCreated, IssueStatusChanged)
│
├── Listeners/                   ← Event dinleyicileri
│   ├── RecalculateEpicCompletion.php
│   ├── UpdateBurndownSnapshot.php
│   ├── SendStatusChangeNotification.php
│   ├── LogActivity.php
│   └── ...
│
├── Policies/                    ← Yetkilendirme (RBAC)
│   ├── ProjectPolicy.php
│   ├── EpicPolicy.php
│   ├── UserStoryPolicy.php
│   ├── TaskPolicy.php
│   ├── SprintPolicy.php
│   ├── IssuePolicy.php
│   └── AttachmentPolicy.php
│
├── Livewire/                    ← UI Component'ler (İNCE — sadece Service çağırır)
│   ├── Auth/                    (LoginForm, RegisterForm)
│   ├── Project/                 (ProjectList, ProjectSettings, MemberManager)
│   ├── Scrum/                   (Backlog, SprintBoard, TaskBoard, EpicList)
│   ├── Issue/                   (IssueList, IssueDetail, IssueForm)
│   ├── Analytics/               (BurndownChart, VelocityChart)
│   └── Notification/            (NotificationPanel, NotificationBell)
│
├── Traits/                      ← Yatay davranışlar (business logic YASAK)
│   ├── BelongsToProject.php
│   ├── HasStateMachine.php
│   └── Auditable.php
│
├── Middleware/
│   ├── EnsureProjectMember.php
│   └── EnsureProjectRole.php
│
└── Providers/
    ├── AuthServiceProvider.php  (Policy registration)
    └── EventServiceProvider.php (Event-Listener mapping)

routes/
├── web.php                      (Livewire sayfa route'ları)
├── api.php                      (REST API — tek dosya, Route::group ile organize)
└── channels.php                 (WebSocket channel authorization)

database/
├── migrations/                  (TEK YERDE — sıralama problemi yok)
├── seeders/
└── factories/

tests/
├── Feature/
│   ├── Auth/                    (LoginTest, RegisterTest)
│   ├── Project/                 (ProjectCrudTest, MembershipTest)
│   ├── Scrum/                   (SprintWorkflowTest, BacklogTest, StateMachineTest)
│   ├── Issue/                   (IssueCrudTest)
│   └── Rbac/                    (OwnerPermissionTest, ModeratorPermissionTest, MemberPermissionTest)
├── Unit/
│   ├── Services/                (BurndownServiceTest, VelocityServiceTest, EpicCompletionTest)
│   └── Actions/                 (DetectScopeChangeActionTest, ...)
└── Livewire/
    ├── SprintBoardTest.php
    └── ...
```

---

## REST API Endpoint Yapısı

```
Auth:
  POST   /api/auth/register
  POST   /api/auth/login
  POST   /api/auth/logout
  GET    /api/auth/me

Projects:
  GET    /api/projects
  POST   /api/projects
  GET    /api/projects/{slug}
  PUT    /api/projects/{slug}
  DELETE /api/projects/{slug}

Members:
  GET    /api/projects/{slug}/members
  POST   /api/projects/{slug}/members
  DELETE /api/projects/{slug}/members/{userId}

Epics:
  GET    /api/projects/{slug}/epics
  POST   /api/projects/{slug}/epics
  GET    /api/projects/{slug}/epics/{id}
  PUT    /api/projects/{slug}/epics/{id}
  DELETE /api/projects/{slug}/epics/{id}

User Stories:
  GET    /api/projects/{slug}/stories
  POST   /api/projects/{slug}/stories
  GET    /api/projects/{slug}/stories/{id}
  PUT    /api/projects/{slug}/stories/{id}
  DELETE /api/projects/{slug}/stories/{id}
  POST   /api/projects/{slug}/stories/{id}/move-to-sprint

Sprints:
  GET    /api/projects/{slug}/sprints
  POST   /api/projects/{slug}/sprints
  GET    /api/projects/{slug}/sprints/{id}
  PUT    /api/projects/{slug}/sprints/{id}
  DELETE /api/projects/{slug}/sprints/{id}
  POST   /api/projects/{slug}/sprints/{id}/start
  POST   /api/projects/{slug}/sprints/{id}/close

Tasks:
  GET    /api/stories/{storyId}/tasks
  POST   /api/stories/{storyId}/tasks
  PUT    /api/tasks/{id}
  DELETE /api/tasks/{id}

Issues:
  GET    /api/projects/{slug}/issues
  POST   /api/projects/{slug}/issues
  GET    /api/projects/{slug}/issues/{id}
  PUT    /api/projects/{slug}/issues/{id}
  DELETE /api/projects/{slug}/issues/{id}

Attachments:
  POST   /api/attachments          (polymorphic upload)
  DELETE /api/attachments/{id}

Notifications:
  GET    /api/notifications
  POST   /api/notifications/mark-read

Analytics:
  GET    /api/projects/{slug}/sprints/{id}/burndown
  GET    /api/projects/{slug}/velocity
```

### HTTP Status Kodları (Zorunlu)

| Kod | Kullanım |
|-----|----------|
| 200 | Başarılı GET/PUT |
| 201 | Başarılı POST (kaynak oluşturma) |
| 204 | Başarılı DELETE |
| 401 | Authenticate olmamış kullanıcı |
| 403 | Yetkisiz erişim (Policy reject) |
| 404 | Kaynak bulunamadı |
| 422 | Validation hatası |

### WebSocket Channels

- `private-project.{id}` — Board değişiklikleri, yeni story/task/issue
- `private-user.{id}` — Kişisel bildirimler

---

## Docker Compose Altyapısı

| Servis | Image | Port | Amaç |
|--------|-------|------|------|
| `app` | PHP 8.3 + Laravel | 8000 | Ana uygulama |
| `reverb` | (aynı image, farklı entrypoint) | 8080 | WebSocket server |
| `queue` | (aynı image) | — | Laravel Queue worker (bildirimler, analytics) |
| `scheduler` | (aynı image) | — | Laravel Scheduler (cron, burndown snapshot) |
| `postgres` | PostgreSQL 16 | 5432 | Veritabanı (prod) |
| `redis` | Redis 7 | 6379 | Cache, session, queue, broadcast |
| `minio` | MinIO | 9000/9001 | Dosya depolama (S3-compat) |
| `nginx` | Nginx | 80/443 | Reverse proxy |

---

## TDD Geliştirme Stratejisi

### Geliştirme Döngüsü: Red → Green → Refactor

| Test Tipi | Kapsam | Min. Sayı | Örnekler |
|-----------|--------|-----------|----------|
| **Feature Test** | HTTP/Livewire uçtan uca | 20+ | Login/logout, RBAC, Sprint workflow, State Machine geçişleri |
| **Unit Test** | Service/Action iş mantığı | 10+ | BurndownService, VelocityService, EpicCompletion, ScopeChange |
| **Livewire Test** | Component davranışı | 10+ | Board drag-drop, form submission, real-time update |
| **Policy Test** | Yetkilendirme | 10+ | Her rol × her işlem kombinasyonu |

### Zorunlu Feature Test Senaryoları

1. Login olmadan korumalı alanlara erişilemez
2. Üye olmayan kullanıcı projeye erişemez
3. Member, User Story oluşturamaz
4. Member, başka kullanıcıya task atayamaz
5. Moderator, projeyi silemez
6. Owner, üye ekleyebilir ve rol değiştirebilir
7. Sprint scope change doğru işaretlenir
8. State Machine geçersiz geçişi reddeder
9. Epic tamamlanma yüzdesi doğru hesaplanır
10. Maksimum üye limiti çalışır (varsa)

### Zorunlu Unit Test Senaryoları

1. `BurndownService` — ideal ve gerçek çizgi hesaplaması
2. `VelocityService` — son N sprint ortalaması
3. `EpicService` — tamamlanma yüzdesi (0%, 50%, 100%)
4. `SprintService` — scope change algılama
5. `UserStoryService` — çoklu rol puanlama toplamı

---

## MVP Kapsamı

| Modül | Dahil | Not |
|-------|:-----:|-----|
| Scrum Board | ✅ | Sprint, Backlog, Burndown |
| Issue Tracker | ✅ | Bug, Question, Enhancement |
| Bildirim Sistemi | ✅ | In-App only |
| Dosya Ekleri | ✅ | MinIO, polymorphic |
| Kanban Board | ❌ | İleride eklenecek, mimari buna açık |
| Wiki/Dokümantasyon | ❌ | İleride eklenecek |
| OAuth Social Login | ❌ | İleride Socialite ile eklenebilir |

---

## DOCS Klasörü Dosya Listesi (14 Doküman)

| # | Dosya | İçerik |
|---|-------|--------|
| 1 | `01-ARCHITECTURE_OVERVIEW.md` | Teknoloji yığını, Service + Action pattern, mimari diyagram, karar gerekçeleri |
| 2 | `02-DOMAIN_MODEL.md` | Entity tanımları, ilişki diyagramı (ER), attribute listesi, value object'ler |
| 3 | `03-STATE_MACHINE.md` | Her entity için durum geçişleri, domain event'ler, geçiş kuralları |
| 4 | `04-BUSINESS_RULES.md` | Sprint scope change, estimation mantığı, epic tamamlanma yüzdesi, burndown hesaplaması |
| 5 | `05-RBAC_PERMISSIONS.md` | Owner/Moderator/Member yetki matrisi, Policy yapısı, middleware akışı |
| 6 | `06-DATABASE_SCHEMA.md` | Tablo tanımları, migration planı, indeksler, JSONB custom fields stratejisi |
| 7 | `07-API_DESIGN.md` | REST endpoint listesi, request/response, WebSocket channel'ları, HTTP status kodları |
| 8 | `08-PROJECT_STRUCTURE.md` | Laravel dizin yapısı, namespace-based gruplandırma, Service/Action konvansiyonları |
| 9 | `09-INFRASTRUCTURE.md` | Docker Compose yapılandırması, environment yönetimi, deployment |
| 10 | `10-ANALYTICS_ENGINE.md` | Burndown chart formülleri, velocity hesaplama, sprint raporlama |
| 11 | `11-NOTIFICATION_SYSTEM.md` | Event-driven bildirim akışı, bildirim tipleri, real-time broadcast |
| 12 | `12-FILE_MANAGEMENT.md` | MinIO entegrasyonu, upload/download akışı, dosya kısıtlamaları |
| 13 | `13-TESTING_STRATEGY.md` | TDD yaklaşımı, Feature/Unit/Livewire/Policy test senaryoları |
| 14 | `14-CODING_STANDARDS.md` | Yasaklar listesi, konvansiyonlar, Query Scope kuralları, review checklist |

---

## Doğrulama (Verification)

- Her doküman `03-B-architecture-layers.md` kurallarına uygunluk kontrolünden geçecek
- `projeKuralları` yasaklar listesi `14-CODING_STANDARDS.md`'de tablo halinde listelenecek
- Katman ihlali kontrolü: Hiçbir Controller'da direkt Model/Action çağrısı olmamalı
- Policy ↔ RBAC matris tutarlılığı doğrulanacak
- Test senaryoları ↔ business rule'lar birebir eşleşmeli
- Docker Compose servislerinin birbiriyle network bağlantıları doğrulanacak

---

## Kararlar Özeti

| Karar | Gerekçe |
|-------|---------|
| Namespace-based gruplandırma (modüler değil) | Modüller arası bağımlılık yüksek, tek geliştirici, monolith deploy, migration sıralama problemi yok |
| Lightweight Clean Architecture | Clean Arch prensipleri uygulanıyor ama strict soyutlamalar (Repository, pure Entity, DTO) bu ölçek için overengineering |
| Service + Action katman pattern'i | Her iki kural dosyasının zorunlu kıldığı mimari. Katman atlama yasak |
| Session-based auth | projeKuralları zorunluluğu + Livewire doğal uyumu |
| TDD yaklaşımı | Test yazmadan proje kabul edilmez kuralına uyum + verimlilik |
| 14 doküman | 2 yeni doküman eklendi (Testing Strategy + Coding Standards) |
| Livewire Component = Thin Controller | İş mantığı Service'te, component sadece state + UI |
| SQLite (dev) → PostgreSQL (prod) | Laravel DB abstraction ile sorunsuz geçiş |
| MinIO (dosya depolama) | Self-hosted S3, Docker Compose uyumlu, ölçeklenebilir |
| Laravel Reverb (WebSocket) | Native, ücretsiz, Livewire + Echo entegrasyonu |
