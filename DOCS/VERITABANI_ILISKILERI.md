# Canopy - Veritabanı İlişkileri ve Yapısı (Detaylı Rehber)

---

## İçindekiler

1. [Temel Kavramlar](#1-temel-kavramlar)
2. [Tablo Yapıları](#2-tablo-yapıları)
3. [İlişki Türleri (Relationship Types)](#3-i̇lişki-türleri-relationship-types)
4. [Model Bazında İlişkiler](#4-model-bazında-i̇lişkiler)
5. [Polimorfik İlişkiler](#5-polimorfik-i̇lişkiler)
6. [Pivot Tabloları](#6-pivot-tabloları)
7. [ER Diyagramı (Metin Tabanlı)](#7-er-diyagramı-metin-tabanlı)
8. [Foreign Key Kısıtlamaları](#8-foreign-key-kısıtlamaları)
9. [Index Stratejisi](#9-index-stratejisi)
10. [Enum Alanları ve Anlamları](#10-enum-alanları-ve-anlamları)
11. [UUID Kullanımı](#11-uuid-kullanımı)
12. [Soft Delete Mekanizması](#12-soft-delete-mekanizması)
13. [Sık Karşılaşılan Sorgular](#13-sık-karşılaşılan-sorgular)

---

## 1. Temel Kavramlar

Veritabanı ilişkilerini anlamak için önce birkaç temel kavramı bilmemiz gerekiyor:

### Primary Key (Birincil Anahtar)
Her tablodaki **benzersiz** kimlik alanıdır. Bu projede tüm tablolar **UUID** formatında primary key kullanır. UUID, `550e8400-e29b-41d4-a716-446655440000` gibi görünen, rastgele üretilen benzersiz bir string'dir.

### Foreign Key (Yabancı Anahtar)
Bir tablodaki sütun, başka bir tablodaki satıra referans verir. Örneğin `projects` tablosundaki `owner_id`, `users` tablosundaki bir `id`'ye işaret eder.

### Eloquent Relationship (Eloquent İlişkisi)
Laravel'in ORM sistemi olan Eloquent, veritabanı ilişkilerini PHP kodunda model metotları olarak tanımlamamızı sağlar. Böylece SQL yazmadan ilişkili verilere erişebiliriz.

---

## 2. Tablo Yapıları

### 2.1 `users` — Kullanıcılar

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Benzersiz kullanıcı kimliği |
| `name` | string | Kullanıcının adı |
| `email` | string (unique) | E-posta adresi, benzersiz olmalı |
| `email_verified_at` | timestamp (nullable) | E-posta doğrulanma tarihi |
| `password` | string | Hashlenmiş şifre |
| `avatar` | string (nullable) | Profil fotoğrafı yolu |
| `is_super_admin` | boolean | Süper yönetici mi? (varsayılan: false) |
| `remember_token` | string | "Beni hatırla" için token |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

**Casts (Tip Dönüşümleri):**
```php
'email_verified_at' => 'datetime',   // Carbon nesnesi olarak döner
'password' => 'hashed',              // Otomatik hashlenir
'is_super_admin' => 'boolean',       // true/false olarak döner
```

---

### 2.2 `projects` — Projeler

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Proje kimliği |
| `name` | string | Proje adı |
| `slug` | string (unique) | URL-dostu isim (ör: `my-project`) |
| `description` | text (nullable) | Proje açıklaması |
| `owner_id` | UUID (FK → users) | Proje sahibi |
| `settings` | JSON (nullable) | Proje ayarları (modüller, roller) |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |
| `deleted_at` | timestamp (nullable) | Soft delete tarihi |

**Settings JSON Yapısı Örneği:**
```json
{
  "modules": { "scrum": true, "issues": true },
  "estimation_roles": ["UX", "Design", "Frontend", "Backend"]
}
```

---

### 2.3 `project_memberships` — Proje Üyelikleri (Pivot Tablo)

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Üyelik kimliği |
| `project_id` | UUID (FK → projects) | Hangi proje |
| `user_id` | UUID (FK → users) | Hangi kullanıcı |
| `role` | string (Enum) | Rol: `owner`, `moderator`, `member` |
| `created_at` | timestamp | Katılma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

**Unique Constraint:** `[project_id, user_id]` — Bir kullanıcı bir projede yalnızca bir kez üye olabilir.

---

### 2.4 `epics` — Epikler

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Epic kimliği |
| `project_id` | UUID (FK → projects) | Ait olduğu proje |
| `title` | string | Epic başlığı |
| `description` | text (nullable) | Açıklama |
| `color` | string(7) | Renk kodu (varsayılan: `#6366F1`) |
| `status` | string (Enum) | Durum: `new`, `in_progress`, `done` |
| `completion_percentage` | unsigned int | Tamamlanma yüzdesi (0-100) |
| `order` | unsigned int | Sıralama numarası |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

---

### 2.5 `sprints` — Sprintler

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Sprint kimliği |
| `project_id` | UUID (FK → projects) | Ait olduğu proje |
| `name` | string | Sprint adı (ör: "Sprint 1") |
| `start_date` | date | Başlangıç tarihi |
| `end_date` | date | Bitiş tarihi |
| `status` | string (Enum) | Durum: `planning`, `active`, `closed` |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

---

### 2.6 `user_stories` — Kullanıcı Hikayeleri

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Hikaye kimliği |
| `project_id` | UUID (FK → projects) | Ait olduğu proje |
| `epic_id` | UUID (FK → epics, nullable) | Ait olduğu epic (opsiyonel) |
| `sprint_id` | UUID (FK → sprints, nullable) | Atandığı sprint (null = backlog) |
| `title` | string | Hikaye başlığı |
| `description` | text (nullable) | Açıklama |
| `status` | string (Enum) | Durum: `new`, `in_progress`, `done` |
| `total_points` | decimal(8,2) | Toplam story point |
| `custom_fields` | JSON (nullable) | Özel alanlar |
| `order` | unsigned int | Backlog sıralama numarası |
| `created_by` | UUID (FK → users) | Oluşturan kullanıcı |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

> **Not:** `sprint_id` null olan hikayeler **backlog**'dadır. Bir sprint'e atandığında bu alan dolar.

---

### 2.7 `story_points` — Hikaye Puanları

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Puan kimliği |
| `user_story_id` | UUID (FK → user_stories) | Hangi hikaye |
| `role_name` | string(50) | Rol adı (UX, Design, Frontend, Backend) |
| `points` | decimal(5,2) | Puan değeri |

**Unique Constraint:** `[user_story_id, role_name]` — Her hikayede bir rol yalnızca 1 kez puanlanır.

> **Timestamps yok:** Bu tablo `$timestamps = false` kullanır çünkü puan bilgisi ya var ya yok, güncelleme tarihi gereksiz.

---

### 2.8 `sprint_scope_changes` — Sprint Kapsam Değişiklikleri

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Değişiklik kimliği |
| `sprint_id` | UUID (FK → sprints) | Hangi sprint |
| `user_story_id` | UUID (FK → user_stories) | Hangi hikaye |
| `change_type` | string | Değişiklik türü: `added` veya `removed` |
| `changed_at` | timestamp | Değişiklik tarihi |
| `changed_by` | UUID (FK → users) | Değişikliği yapan kullanıcı |

> Bu tablo, aktif bir sprint sırasında hikaye eklenip çıkarıldığını takip eder. Burndown chart hesaplamaları için kritiktir.

---

### 2.9 `tasks` — Görevler

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Görev kimliği |
| `user_story_id` | UUID (FK → user_stories) | Ait olduğu hikaye |
| `title` | string | Görev başlığı |
| `description` | text (nullable) | Açıklama |
| `status` | string (Enum) | Durum: `new`, `in_progress`, `done` |
| `assigned_to` | UUID (FK → users, nullable) | Atanan kullanıcı |
| `created_by` | UUID (FK → users) | Oluşturan kullanıcı |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

> **İş Kuralı (BR-16):** Bir görev `new` → `in_progress` geçişi yapabilmek için **mutlaka atanmış** olmalıdır (`assigned_to` null olamaz).

---

### 2.10 `issues` — Sorunlar / Hatalar

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Sorun kimliği |
| `project_id` | UUID (FK → projects) | Ait olduğu proje |
| `title` | string | Sorun başlığı |
| `description` | text (nullable) | Açıklama |
| `type` | string (Enum) | Tür: `bug`, `question`, `enhancement` |
| `priority` | string (Enum) | Öncelik: `low`, `normal`, `high` |
| `severity` | string (Enum) | Ciddiyet: `wishlist`, `minor`, `critical` |
| `status` | string (Enum) | Durum: `new`, `in_progress`, `done` |
| `assigned_to` | UUID (FK → users, nullable) | Atanan kullanıcı |
| `created_by` | UUID (FK → users) | Oluşturan kullanıcı |
| `created_at` | timestamp | Oluşturulma tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

---

### 2.11 `attachments` — Dosya Ekleri

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Ek kimliği |
| `attachable_type` | string | İlişkili model tipi (ör: `App\Models\Issue`) |
| `attachable_id` | UUID | İlişkili model kimliği |
| `filename` | string | Orijinal dosya adı |
| `path` | string(500) | S3'teki dosya yolu |
| `mime_type` | string(100) | Dosya MIME tipi (ör: `image/png`) |
| `size` | unsigned big int | Dosya boyutu (byte) |
| `uploaded_by` | UUID (FK → users) | Yükleyen kullanıcı |
| `created_at` | timestamp | Yükleme tarihi |
| `updated_at` | timestamp | Güncellenme tarihi |

> Bu tablo **polimorfik** bir ilişki kullanır. `attachable_type` + `attachable_id` ile `UserStory`, `Task` veya `Issue` modeline bağlanır.

---

### 2.12 `notifications` — Bildirimler

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Bildirim kimliği |
| `user_id` | UUID (FK → users) | Alıcı kullanıcı |
| `type` | string | Bildirim türü (ör: `task_assigned`, `member_added`) |
| `data` | JSON | Bildirim verileri |
| `read_at` | timestamp (nullable) | Okunma tarihi (null = okunmamış) |
| `created_at` | timestamp | Oluşturulma tarihi |

> **UPDATED_AT yok:** Bu tablo `UPDATED_AT = null` kullanır. Bildirimler oluşturulduktan sonra yalnızca `read_at` güncellenir.

---

### 2.13 `activity_logs` — Aktivite Kayıtları

| Sütun | Tür | Açıklama |
|-------|-----|----------|
| `id` | UUID (PK) | Log kimliği |
| `project_id` | UUID (FK → projects) | Hangi projede |
| `user_id` | UUID (FK → users) | İşlemi yapan kullanıcı |
| `action` | string | İşlem: `created`, `updated`, `deleted`, `status_changed` |
| `subject_type` | string | İlişkili model tipi |
| `subject_id` | UUID | İlişkili model kimliği |
| `changes` | JSON (nullable) | Değişiklik detayları |
| `created_at` | timestamp | İşlem tarihi |

> **Polimorfik:** `subject_type` + `subject_id` ile herhangi bir modele bağlanır (MorphTo).

---

### 2.14 Sistem Tabloları

| Tablo | Açıklama |
|-------|----------|
| `password_reset_tokens` | Şifre sıfırlama token'ları |
| `sessions` | Kullanıcı oturumları |
| `cache` | Uygulama önbellek verileri |
| `cache_locks` | Önbellek kilitleri |
| `jobs` / `job_batches` / `failed_jobs` | Queue (kuyruk) sistemi tabloları |
| `personal_access_tokens` | Sanctum API token'ları |

---

## 3. İlişki Türleri (Relationship Types)

Bu projede kullanılan ilişki türlerini anlayalım:

### 3.1 `HasMany` (Bire-Çok)

Bir model, birden fazla ilişkili kayda sahip olabilir.

```text
Örnek: Bir User birçok Project'e sahip olabilir.
User (1) ──────── (N) Project
         owner_id
```

```php
// User modelinde:
public function ownedProjects(): HasMany
{
    return $this->hasMany(Project::class, 'owner_id');
}

// Kullanım:
$user->ownedProjects;            // Tüm projeleri getirir
$user->ownedProjects()->count(); // Proje sayısını verir
```

### 3.2 `BelongsTo` (Çoktan-Bire)

Bir modelin "sahibi" olan başka bir model.

```text
Örnek: Her Project bir User'a (owner) aittir.
Project ──── owner_id ────→ User
```

```php
// Project modelinde:
public function owner(): BelongsTo
{
    return $this->belongsTo(User::class, 'owner_id');
}

// Kullanım:
$project->owner;       // User nesnesini döndürür
$project->owner->name; // "Selim"
```

### 3.3 `BelongsToMany` (Çoktan-Çoğa)

İki model arasında **pivot tablo** üzerinden çoktan-çoğa ilişki.

```text
User (N) ←── project_memberships ──→ (N) Project
```

```php
// Project modelinde:
public function members()
{
    return $this->belongsToMany(User::class, 'project_memberships')
        ->withPivot('role')        // Pivot tablodaki ek veriyi de getir
        ->withTimestamps();        // created_at, updated_at dâhil et
}

// Kullanım:
$project->members;                     // Tüm üye User'ları
$project->members->first()->pivot->role; // "owner"
```

### 3.4 `MorphMany` + `MorphTo` (Polimorfik İlişki)

Bir model birden fazla farklı modele bağlanabilir. "Attachments" tablosu buna örnektir.

```text
UserStory ──┐
Task ───────┤──→ Attachment (attachable_type + attachable_id)
Issue ──────┘
```

```php
// Issue modelinde:
public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable');
}

// Attachment modelinde:
public function attachable(): MorphTo
{
    return $this->morphTo();
}

// Kullanım:
$issue->attachments;              // Bu issue'ya ait tüm dosyalar
$attachment->attachable;          // Issue, Task veya UserStory olabilir
```

> **Polimorfik ne demek?** `attachable_type` sütununda model sınıfının tam adı tutulur (ör: `App\Models\Issue`), `attachable_id`'de ise o modelin UUID'si. Böylece tek tablo ile birden fazla modele dosya eklenebilir.

---

## 4. Model Bazında İlişkiler

### 4.1 User (Kullanıcı)

```
User
├── ownedProjects()       → HasMany(Project)         // Sahip olduğu projeler
├── projectMemberships()  → HasMany(ProjectMembership) // Üyelikleri
├── assignedTasks()       → HasMany(Task)             // Atanan görevleri
├── assignedIssues()      → HasMany(Issue)            // Atanan sorunları
├── notifications()       → HasMany(Notification)     // Bildirimleri
└── activityLogs()        → HasMany(ActivityLog)      // Aktivite kayıtları
```

**User aynı zamanda şu modeller tarafından referans alınır:**
- `Project.owner_id` → Proje sahibi
- `Task.assigned_to` → Göreve atanan kişi
- `Task.created_by` → Görevi oluşturan kişi
- `Issue.assigned_to` → Soruna atanan kişi
- `Issue.created_by` → Sorunu oluşturan kişi
- `UserStory.created_by` → Hikayeyi oluşturan kişi
- `Attachment.uploaded_by` → Dosyayı yükleyen kişi
- `SprintScopeChange.changed_by` → Kapsam değişikliğini yapan kişi

---

### 4.2 Project (Proje)

```
Project
├── owner()           → BelongsTo(User)              // Proje sahibi
├── memberships()     → HasMany(ProjectMembership)    // Üyelik kayıtları
├── members()         → BelongsToMany(User)           // Üyeler (pivot ile)
├── epics()           → HasMany(Epic)                 // Epikleri
├── userStories()     → HasMany(UserStory)            // Kullanıcı hikayeleri
├── sprints()         → HasMany(Sprint)               // Sprintleri
├── issues()          → HasMany(Issue)                // Sorunları
└── activityLogs()    → HasMany(ActivityLog)          // Aktivite kayıtları
```

**Project, sistemdeki en merkezi modeldir.** Neredeyse her şey bir projeye bağlıdır.

---

### 4.3 ProjectMembership (Proje Üyeliği)

```
ProjectMembership
├── project() → BelongsTo(Project)   // Hangi proje
└── user()    → BelongsTo(User)      // Hangi kullanıcı
```

Bu model, `User` ile `Project` arasındaki **çoktan-çoğa** ilişkiyi sağlayan pivot tablodur. Ayrıca `role` bilgisini de taşır.

---

### 4.4 Epic (Epik)

```
Epic
├── project()      → BelongsTo(Project) [Trait: BelongsToProject]
└── userStories()  → HasMany(UserStory)  // Bu epic altındaki hikayeler
```

**Epic, ilişkili hikayelerin durumuna göre otomatik olarak tamamlanma yüzdesini günceller.**

---

### 4.5 Sprint

```
Sprint
├── project()       → BelongsTo(Project) [Trait: BelongsToProject]
├── userStories()   → HasMany(UserStory)           // Sprint'e atanan hikayeler
└── scopeChanges()  → HasMany(SprintScopeChange)   // Kapsam değişiklikleri
```

---

### 4.6 UserStory (Kullanıcı Hikayesi)

```
UserStory
├── project()      → BelongsTo(Project) [Trait: BelongsToProject]
├── epic()         → BelongsTo(Epic)          // Ait olduğu epic (nullable)
├── sprint()       → BelongsTo(Sprint)        // Atandığı sprint (nullable)
├── creator()      → BelongsTo(User)          // Oluşturan kullanıcı
├── tasks()        → HasMany(Task)            // Alt görevleri
├── storyPoints()  → HasMany(StoryPoint)      // Rol bazlı puanlar
└── attachments()  → MorphMany(Attachment)    // Ek dosyalar [Polimorfik]
```

**UserStory, projede en çok ilişkisi olan modeldir.** Hem yukarı (Epic, Sprint, Project), hem yanlara (Task, StoryPoint), hem de polimorfik (Attachment) ilişkileri vardır.

---

### 4.7 Task (Görev)

```
Task
├── userStory()    → BelongsTo(UserStory)     // Ait olduğu hikaye
├── assignee()     → BelongsTo(User)          // Atanan kullanıcı
├── creator()      → BelongsTo(User)          // Oluşturan kullanıcı
└── attachments()  → MorphMany(Attachment)    // Ek dosyalar [Polimorfik]
```

> **Erişim Kısayolu:** `$task->project`, `getProjectAttribute()` accessor'ı sayesinde `$task->userStory->project` üzerinden dolaylı erişim sağlar.

---

### 4.8 Issue (Sorun/Hata)

```
Issue
├── project()      → BelongsTo(Project) [Trait: BelongsToProject]
├── assignee()     → BelongsTo(User)          // Atanan kullanıcı
├── creator()      → BelongsTo(User)          // Oluşturan kullanıcı
└── attachments()  → MorphMany(Attachment)    // Ek dosyalar [Polimorfik]
```

---

### 4.9 StoryPoint (Hikaye Puanı)

```
StoryPoint
└── userStory() → BelongsTo(UserStory)     // Ait olduğu hikaye
```

---

### 4.10 SprintScopeChange (Sprint Kapsam Değişikliği)

```
SprintScopeChange
├── sprint()         → BelongsTo(Sprint)      // Hangi sprint
├── userStory()      → BelongsTo(UserStory)   // Hangi hikaye
└── changedByUser()  → BelongsTo(User)        // Kim değiştirdi
```

---

### 4.11 Attachment (Dosya Eki)

```
Attachment
├── attachable() → MorphTo()                  // UserStory, Task veya Issue
└── uploader()   → BelongsTo(User)            // Yükleyen kullanıcı
```

---

### 4.12 Notification (Bildirim)

```
Notification
└── user() → BelongsTo(User)                  // Alıcı kullanıcı
```

---

### 4.13 ActivityLog (Aktivite Kaydı)

```
ActivityLog
├── project() → BelongsTo(Project)            // Hangi projede
├── user()    → BelongsTo(User)               // Kim yaptı
└── subject() → MorphTo()                     // Neyi etkiledi [Polimorfik]
```

---

## 5. Polimorfik İlişkiler

Bu projede **2 adet polimorfik ilişki** kullanılır:

### 5.1 Attachment (Dosya Ekleri) — `MorphMany / MorphTo`

```
┌─────────────┐     attachable_type = "App\Models\UserStory"
│  UserStory   │────→ attachable_id  = "uuid-of-story"
└─────────────┘            │
                           ▼
                    ┌──────────────┐
                    │  Attachment  │
                    └──────────────┘
                           ▲
                           │
┌─────────────┐     attachable_type = "App\Models\Task"
│    Task      │────→ attachable_id  = "uuid-of-task"
└─────────────┘

┌─────────────┐     attachable_type = "App\Models\Issue"
│    Issue     │────→ attachable_id  = "uuid-of-issue"
└─────────────┘
```

**Avantajı:** Tek bir `attachments` tablosu ile 3 farklı modele dosya ekleyebiliyoruz. Her model için ayrı tablo oluşturmaya gerek kalmıyor.

### 5.2 ActivityLog (Aktivite Kayıtları) — `MorphTo`

```
subject_type = "App\Models\UserStory" | "App\Models\Task" | "App\Models\Sprint" | ...
subject_id   = ilgili modelin UUID'si
```

Herhangi bir model üzerindeki değişiklik (oluşturma, güncelleme, silme, durum değişikliği) bu tabloya kaydedilir.

---

## 6. Pivot Tabloları

### 6.1 `project_memberships` — User ↔ Project

Bu, klasik bir **çoktan-çoğa** pivot tablodur:

```
User (N) ←──── project_memberships ────→ (N) Project
                    │
                    ├── role (owner | moderator | member)
                    ├── created_at
                    └── updated_at
```

Ancak bu projede pivot tablosu **kendi modeline** (`ProjectMembership`) sahiptir. Bunun sebebi:
- `role` alanı üzerinde iş kuralları var (yetkilendirme, roller hiyerarşisi)
- `isOwner()`, `isAtLeast()` gibi helper metotlar gerekli
- Doğrudan `HasMany` ile de erişilebilir olmalı

```php
// İki farklı erişim yolu:
$project->members;       // BelongsToMany → User collection
$project->memberships;   // HasMany → ProjectMembership collection
```

---

## 7. ER Diyagramı (Metin Tabanlı)

```
┌──────────┐     ┌────────────────────┐     ┌──────────┐
│  User    │────→│ ProjectMembership  │←────│ Project  │
│          │  1:N│   (pivot + model)  │N:1  │          │
│          │     └────────────────────┘     │          │
│          │                                │          │
│          │←──── owner_id ─────────────────│          │
│          │                                │          │
│          │     ┌────────────────────┐     │          │
│          │     │      Epic         │←────│          │
│          │     └────────┬───────────┘     │          │
│          │              │ 1:N             │          │
│          │     ┌────────▼───────────┐     │          │
│          │←────│    UserStory       │←────│          │
│          │ created_by              │      │          │
│          │     │                    │      │          │
│          │     │  ┌─── sprint_id ──┐│     │          │
│          │     │  │                ││     │          │
│          │     └──┼────────────────┘│     │          │
│          │        │                 │     │          │
│          │     ┌──▼─────────────┐   │     │          │
│          │     │    Sprint      │←──┼─────│          │
│          │     └──┬─────────────┘   │     └──────────┘
│          │        │ 1:N             │
│          │     ┌──▼───────────────┐ │
│          │←────│ SprintScopeChange│ │
│          │ changed_by            │ │
│          │     └──────────────────┘ │
│          │                          │
│          │     ┌────────────────┐   │
│          │←────│     Task       │←──┘  1:N (userStory → tasks)
│          │ assigned_to + created_by │
│          │     └────────────────┘
│          │
│          │     ┌────────────────┐     ┌──────────┐
│          │←────│     Issue      │←────│ Project  │
│          │     └────────────────┘     └──────────┘
│          │
│          │     ┌────────────────┐
│          │←────│  Notification  │
│          │ user_id              │
│          │     └────────────────┘
│          │
│          │     ┌────────────────┐
│          │←────│  ActivityLog   │
│          │ user_id              │
│          │     └────────────────┘
│          │
│          │     ┌────────────────┐
│          │←────│  Attachment    │  ←── (MorphMany from UserStory, Task, Issue)
│          │ uploaded_by          │
└──────────┘     └────────────────┘

    ┌────────────────┐
    │   StoryPoint   │←── user_story_id ──→ UserStory  (1:N)
    └────────────────┘
```

---

## 8. Foreign Key Kısıtlamaları

### Cascade On Delete (`cascadeOnDelete`)
Üst kayıt silinirse, alt kayıtlar da otomatik silinir.

| FK | Davranış | Açıklama |
|----|----------|----------|
| `projects.owner_id → users` | CASCADE | User silinirse projeleri de silinir |
| `project_memberships.project_id → projects` | CASCADE | Proje silinirse üyelikler de silinir |
| `project_memberships.user_id → users` | CASCADE | User silinirse üyelikleri de silinir |
| `epics.project_id → projects` | CASCADE | Proje silinirse epic'ler de silinir |
| `sprints.project_id → projects` | CASCADE | Proje silinirse sprintler de silinir |
| `tasks.user_story_id → user_stories` | CASCADE | Hikaye silinirse görevleri de silinir |
| `issues.created_by → users` | CASCADE | Creator silinirse issue'lar da silinir |
| `story_points.user_story_id → user_stories` | CASCADE | Hikaye silinirse puanlar da silinir |
| `sprint_scope_changes.sprint_id → sprints` | CASCADE | Sprint silinirse değişiklik kayıtları da silinir |

### Null On Delete (`nullOnDelete`)
Üst kayıt silinirse, ilişki alanı `null` olur (kayıt korunur).

| FK | Davranış | Açıklama |
|----|----------|----------|
| `user_stories.epic_id → epics` | NULL | Epic silinirse hikaye kalır, epic_id null olur |
| `user_stories.sprint_id → sprints` | NULL | Sprint silinirse hikaye backlog'a döner |
| `tasks.assigned_to → users` | NULL | Atanan kullanıcı silinirse görev kalır (atanmamış) |
| `issues.assigned_to → users` | NULL | Atanan kullanıcı silinirse sorun kalır (atanmamış) |

---

## 9. Index Stratejisi

Veritabanında sık sorgulanan sütunlara **index** koyarak performansı artırıyoruz:

| Tablo | Index | Neden |
|-------|-------|-------|
| `projects` | `owner_id` | Kullanıcının projelerini hızlı bul |
| `project_memberships` | `[project_id, user_id]` UNIQUE | Tekil üyelik, hızlı üyelik kontrolü |
| `project_memberships` | `user_id` | Kullanıcının üyeliklerini hızlı bul |
| `epics` | `[project_id, status]` | Projedeki epic'leri duruma göre filtrele |
| `sprints` | `[project_id, status]` | Projedeki aktif sprint'i hızlı bul |
| `user_stories` | `[project_id, status]` | Hikaye filtreleme |
| `user_stories` | `sprint_id` | Sprint'teki hikayeleri hızlı bul |
| `user_stories` | `epic_id` | Epic'teki hikayeleri hızlı bul |
| `story_points` | `[user_story_id, role_name]` UNIQUE | Tekil puan, hızlı puan erişimi |
| `sprint_scope_changes` | `[sprint_id, changed_at]` | Kronolojik kapsam değişikliği sorgusu |
| `tasks` | `user_story_id` | Hikayenin görevlerini hızlı bul |
| `tasks` | `[assigned_to, status]` | Kullanıcının görevlerini duruma göre filtrele |
| `issues` | `[project_id, status]` | Sorun filtreleme |
| `issues` | `assigned_to` | Kullanıcıya atanan sorunları bul |
| `notifications` | `[user_id, read_at]` | Okunmamış bildirimleri hızlı bul |
| `activity_logs` | `[project_id, created_at]` | Proje aktivite geçmişi |
| `activity_logs` | `[subject_type, subject_id]` | Belirli bir kaydın aktivite geçmişi |

---

## 10. Enum Alanları ve Anlamları

### IssueStatus / StoryStatus / TaskStatus
| Değer | Türkçe | Renk |
|-------|--------|------|
| `new` | Yeni | Gri (#6B7280) |
| `in_progress` | Devam Ediyor | Mavi (#3B82F6) |
| `done` | Tamamlandı | Yeşil (#10B981) |

### SprintStatus
| Değer | Türkçe | Renk |
|-------|--------|------|
| `planning` | Planlama | Sarı (#F59E0B) |
| `active` | Aktif | Mavi (#3B82F6) |
| `closed` | Kapatıldı | Gri (#6B7280) |

### ProjectRole
| Değer | Türkçe | Hiyerarşi (rank) |
|-------|--------|-------------------|
| `owner` | Proje Sahibi | 3 (en yüksek) |
| `moderator` | Yardımcı Yönetici | 2 |
| `member` | Üye | 1 (en düşük) |

### IssueType
| Değer | Türkçe | Renk |
|-------|--------|------|
| `bug` | Hata | Kırmızı |
| `question` | Soru | Mor |
| `enhancement` | İyileştirme | Yeşil |

### IssuePriority
| Değer | Türkçe | Renk |
|-------|--------|------|
| `low` | Düşük | Gri |
| `normal` | Normal | Sarı |
| `high` | Yüksek | Kırmızı |

### IssueSeverity
| Değer | Türkçe | Renk |
|-------|--------|------|
| `wishlist` | İstenen | Gri |
| `minor` | Küçük | Sarı |
| `critical` | Kritik | Kırmızı |

---

## 11. UUID Kullanımı

Tüm modeller `HasUuids` trait'ini kullanır:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Project extends Model
{
    use HasUuids;
}
```

**Neden UUID?**
- **Güvenlik:** Tahmin edilemez (auto-increment `1, 2, 3...` gibi değil)
- **Dağıtık Sistemler:** Birden fazla sunucuda bile çakışmadan ID üretilir
- **API Güvenliği:** Kullanıcılar diğer kayıtların ID'lerini tahmin edemez

**Migration'da:**
```php
$table->uuid('id')->primary();          // UUID primary key
$table->foreignUuid('owner_id')          // UUID foreign key
    ->constrained('users')
    ->cascadeOnDelete();
```

---

## 12. Soft Delete Mekanizması

Yalnızca `Project` modeli **soft delete** kullanır:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;
}
```

**Nasıl çalışır?**
- `$project->delete()` çağrıldığında kayıt veritabanından silinmez
- `deleted_at` sütununa mevcut tarih yazılır
- Normal sorgular silinmiş kayıtları otomatik hariç tutar
- `Project::withTrashed()` ile silinmişleri de dahil edebilirsiniz
- `$project->restore()` ile geri alabilirsiniz

**Neden sadece Project'te?** Bir proje silindiğinde geri alınabilir olması isteniyor (BR-15). Diğer kayıtlar ise cascade ile kalıcı olarak silinir.

---

## 13. Sık Karşılaşılan Sorgular

### Bir kullanıcının tüm projelerini getirme
```php
// Yöntem 1: Üyelikler üzerinden
$projects = Project::query()
    ->forUser($user->id)
    ->get();

// Yöntem 2: Scope kullanarak
$user->projectMemberships()
    ->with('project')
    ->get()
    ->pluck('project');
```

### Backlog'daki hikayeleri sıralı getirme
```php
$stories = UserStory::query()
    ->forProject($project->id)
    ->backlog()           // sprint_id IS NULL
    ->ordered()           // ORDER BY order
    ->with(['tasks', 'epic', 'storyPoints'])
    ->get();
```

### Aktif sprint'in hikayelerini getirme
```php
$activeSprint = Sprint::query()
    ->forProject($project->id)
    ->active()
    ->with(['userStories.tasks.assignee'])
    ->first();
```

### Bir kullanıcıya atanan tüm görevleri getirme
```php
$tasks = Task::query()
    ->assignedTo($user->id)
    ->byStatus(TaskStatus::InProgress)
    ->with('userStory.project')
    ->get();
```

### Okunmamış bildirimleri getirme
```php
$notifications = $user->notifications()
    ->unread()
    ->latest()
    ->paginate(20);
```

### Polimorfik dosya eklerini getirme
```php
// Issue'nun ekleri
$issue->attachments()->with('uploader')->get();

// Herhangi bir ek'in sahip modeli
$attachment->attachable; // Issue, Task veya UserStory
```

---

## Özet: İlişki Haritası (Hızlı Referans)

| Model | Üst (BelongsTo) | Alt (HasMany) | Polimorfik |
|-------|------------------|---------------|------------|
| **User** | — | Projects, Memberships, Tasks, Issues, Notifications, ActivityLogs | — |
| **Project** | User (owner) | Memberships, Epics, Sprints, UserStories, Issues, ActivityLogs | — |
| **ProjectMembership** | Project, User | — | — |
| **Epic** | Project | UserStories | — |
| **Sprint** | Project | UserStories, ScopeChanges | — |
| **UserStory** | Project, Epic?, Sprint?, User (creator) | Tasks, StoryPoints | Attachments (MorphMany) |
| **Task** | UserStory, User (assignee?), User (creator) | — | Attachments (MorphMany) |
| **Issue** | Project, User (assignee?), User (creator) | — | Attachments (MorphMany) |
| **StoryPoint** | UserStory | — | — |
| **SprintScopeChange** | Sprint, UserStory, User (changedBy) | — | — |
| **Attachment** | User (uploader) | — | MorphTo (attachable) |
| **Notification** | User | — | — |
| **ActivityLog** | Project, User | — | MorphTo (subject) |

> **?** = nullable ilişki (foreign key null olabilir)
