# 02 — Domain Model

Sistemin temel varlıkları (entities), ilişkileri ve attribute tanımları.

**İlişkili Dokümanlar:** [Database Schema](./06-DATABASE_SCHEMA.md) | [State Machine](./03-STATE_MACHINE.md) | [Business Rules](./04-BUSINESS_RULES.md)

---

## 1. Entity Hiyerarşisi

Root (kök) nesne her zaman **Project**'tir. Diğer tüm varlıklar doğrudan veya dolaylı olarak projeye bağlıdır.

```
User (bağımsız)
  │
  └──► ProjectMembership ◄── Project (ROOT)
                                │
                ┌───────────────┼───────────────┬──────────────┐
                ▼               ▼               ▼              ▼
              Epic           Sprint           Issue        Attachment
                │               │                          (polymorphic)
                ▼               │
           UserStory ◄──────────┘
                │
          ┌─────┴─────┐
          ▼           ▼
        Task      StoryPoint
```

---

## 2. Entity Tanımları

### 2.1 User

Sistemdeki kayıtlı kullanıcıyı temsil eder. Birden fazla projeye üye olabilir ve her projede farklı role sahip olabilir.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| name | `string(255)` | Kullanıcı adı |
| email | `string(255)` | Unique, login için kullanılır |
| password | `string` | Bcrypt hash |
| avatar | `string(nullable)` | Profil fotoğrafı yolu (MinIO) |
| is_super_admin | `boolean` | Sistem geneli yönetici mi? Default: false |
| email_verified_at | `timestamp(nullable)` | Email doğrulama tarihi |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `hasMany(ProjectMembership)` — Üye olduğu projeler
- `hasMany(Task, 'assigned_to')` — Atanmış görevler
- `hasMany(Issue, 'assigned_to')` — Atanmış sorunlar
- `hasMany(Notification)` — Bildirimleri
- `hasMany(ActivityLog)` — Aktivite geçmişi

**Scopes:**
- `scopeSuperAdmins()` — Sistem yöneticilerini filtrele

---

### 2.2 Project

Sistemin kök varlığı. Tüm iş nesneleri bir projeye aittir.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| name | `string(255)` | Proje adı |
| slug | `string(255)` | URL-friendly unique tanımlayıcı |
| description | `text(nullable)` | Proje açıklaması |
| owner_id | `FK → users` | Proje sahibi |
| settings | `json` | Modül ayarları, özel konfigürasyon |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |
| deleted_at | `timestamp(nullable)` | Soft delete |

**İlişkiler:**
- `belongsTo(User, 'owner_id')` — Proje sahibi
- `hasMany(ProjectMembership)` — Üyelikler
- `hasMany(Epic)` — Destanlar
- `hasMany(UserStory)` — Kullanıcı hikayeleri
- `hasMany(Sprint)` — Sprint'ler
- `hasMany(Issue)` — Sorunlar
- `hasMany(ActivityLog)` — Aktivite geçmişi

**Scopes:**
- `scopeForUser($userId)` — Kullanıcının üye olduğu projeler
- `scopeActive()` — Soft delete'ten geçmemiş projeler

**settings JSON Yapısı:**
```json
{
  "modules": {
    "scrum": true,
    "issues": true
  },
  "estimation_roles": ["UX", "Design", "Frontend", "Backend"],
  "max_members": 50
}
```

---

### 2.3 ProjectMembership

Kullanıcı ile proje arasındaki çoka-çok ilişkiyi ve rol bilgisini taşıyan pivot entity.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| project_id | `FK → projects` | |
| user_id | `FK → users` | |
| role | `enum(owner, moderator, member)` | Proje içi rol |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**Constraints:**
- `UNIQUE(project_id, user_id)` — Bir kullanıcı bir projede tek üyelik

**İlişkiler:**
- `belongsTo(Project)`
- `belongsTo(User)`

---

### 2.4 Epic

Büyük hedefleri temsil eden gruplandırma varlığı. Birden fazla User Story'yi kapsar.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| project_id | `FK → projects` | |
| title | `string(255)` | Epic başlığı |
| description | `text(nullable)` | Detaylı açıklama |
| color | `string(7)` | Hex renk kodu (#FF5733) |
| status | `enum(new, in_progress, done)` | Durum |
| order | `integer` | Sıralama |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(Project)`
- `hasMany(UserStory)`

**Computed (Accessor):**
- `completion_percentage` — İçindeki Done story sayısı / toplam story sayısı × 100

**İş Kuralı:** Tamamlanma yüzdesi otomatik hesaplanır, doğrudan set edilmez. Detay: [04-BUSINESS_RULES.md](./04-BUSINESS_RULES.md#epic-tamamlanma)

---

### 2.5 UserStory

Sistemin en temel değer birimi. Story Point taşır, Sprint'e atanabilir.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| project_id | `FK → projects` | |
| epic_id | `FK → epics (nullable)` | Bağlı Epic |
| sprint_id | `FK → sprints (nullable)` | Atandığı Sprint (null = Backlog'da) |
| title | `string(255)` | Hikaye başlığı |
| description | `text(nullable)` | Detaylı açıklama |
| status | `enum(new, in_progress, done)` | Durum |
| total_points | `decimal(8,2)` | Toplam story point (hesaplanır) |
| custom_fields | `json(nullable)` | Proje bazlı özel alanlar |
| order | `integer` | Backlog/Sprint içi sıralama |
| created_by | `FK → users` | Oluşturan kullanıcı |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(Project)`
- `belongsTo(Epic)` — nullable
- `belongsTo(Sprint)` — nullable
- `belongsTo(User, 'created_by')`
- `hasMany(Task)`
- `hasMany(StoryPoint)`
- `morphMany(Attachment)`

**Scopes:**
- `scopeBacklog()` — `sprint_id IS NULL`
- `scopeInSprint($sprintId)` — Belirli sprint'teki hikayeler
- `scopeByStatus($status)` — Duruma göre filtre

**İş Kuralı:** `total_points` = StoryPoint kayıtlarının toplamı. Detay: [04-BUSINESS_RULES.md](./04-BUSINESS_RULES.md#puanlama)

---

### 2.6 StoryPoint

Bir User Story'nin farklı roller bazında aldığı puanları taşır.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| user_story_id | `FK → user_stories` | |
| role_name | `string(50)` | Puan veren rol (UX, Design, Frontend, Backend) |
| points | `decimal(5,2)` | Puan değeri |

**İlişkiler:**
- `belongsTo(UserStory)`

**İş Kuralı:** `role_name` değerleri proje ayarlarındaki `estimation_roles` listesinden gelir. Toplam puan `UserStory.total_points`'e yazılır.

---

### 2.7 Sprint

Zaman kutulu (time-boxed) çalışma periyodu.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| project_id | `FK → projects` | |
| name | `string(255)` | Sprint adı (örn. "Sprint 14") |
| start_date | `date` | Başlangıç tarihi |
| end_date | `date` | Bitiş tarihi |
| status | `enum(planning, active, closed)` | Sprint durumu |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(Project)`
- `hasMany(UserStory)`
- `hasMany(SprintScopeChange)`

**Scopes:**
- `scopeActive()` — `status = active`
- `scopeClosed()` — `status = closed`

**İş Kuralı:** Bir projede aynı anda yalnızca **1 aktif Sprint** olabilir.

---

### 2.8 SprintScopeChange

Aktif Sprint'e sonradan eklenen veya çıkarılan story'lerin kaydı. Burndown chart için kritik.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| sprint_id | `FK → sprints` | |
| user_story_id | `FK → user_stories` | |
| change_type | `enum(added, removed)` | Ekleme mi çıkarma mı? |
| changed_at | `timestamp` | Değişiklik zamanı |
| changed_by | `FK → users` | Değişikliği yapan kullanıcı |

**İlişkiler:**
- `belongsTo(Sprint)`
- `belongsTo(UserStory)`
- `belongsTo(User, 'changed_by')`

---

### 2.9 Task

User Story'nin teknik alt kırılımı. Kendi durumu vardır ancak puan taşımaz.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| user_story_id | `FK → user_stories` | Bağlı hikaye |
| title | `string(255)` | Görev başlığı |
| description | `text(nullable)` | Detaylı açıklama |
| status | `enum(new, in_progress, done)` | Durum |
| assigned_to | `FK → users (nullable)` | Atanan kullanıcı |
| created_by | `FK → users` | Oluşturan kullanıcı |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(UserStory)`
- `belongsTo(User, 'assigned_to')` — nullable
- `belongsTo(User, 'created_by')`
- `morphMany(Attachment)`

**Scopes:**
- `scopeAssignedTo($userId)` — Kullanıcıya atanmış görevler
- `scopeOverdue()` — Tamamlanmamış görevler (story sprint'i bitmişse)

---

### 2.10 Issue

Hata takip mekanizması. Tip, öncelik ve şiddet seviyesi taşır.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| project_id | `FK → projects` | |
| title | `string(255)` | Issue başlığı |
| description | `text(nullable)` | Detaylı açıklama |
| type | `enum(bug, question, enhancement)` | Issue tipi |
| priority | `enum(low, normal, high)` | Öncelik |
| severity | `enum(wishlist, minor, critical)` | Şiddet seviyesi |
| status | `enum(new, in_progress, done)` | Durum |
| assigned_to | `FK → users (nullable)` | Atanan kullanıcı |
| created_by | `FK → users` | Oluşturan kullanıcı |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(Project)`
- `belongsTo(User, 'assigned_to')` — nullable
- `belongsTo(User, 'created_by')`
- `morphMany(Attachment)`

**Scopes:**
- `scopeOpen()` — `status != done`
- `scopeByPriority($priority)` — Önceliğe göre filtre
- `scopeBySeverity($severity)` — Şiddete göre filtre
- `scopeByType($type)` — Tipe göre filtre

---

### 2.11 Attachment

Polymorphic dosya eki. UserStory, Task ve Issue'ya eklenebilir.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| attachable_type | `string` | Polymorphic tip (UserStory, Task, Issue) |
| attachable_id | `uuid_v7` | Polymorphic ID |
| filename | `string(255)` | Orijinal dosya adı |
| path | `string(500)` | MinIO depolama yolu |
| mime_type | `string(100)` | MIME tipi (image/png, application/pdf) |
| size | `integer` | Dosya boyutu (bytes) |
| uploaded_by | `FK → users` | Yükleyen kullanıcı |
| created_at | `timestamp` | |
| updated_at | `timestamp` | |

**İlişkiler:**
- `morphTo(attachable)` — UserStory, Task veya Issue
- `belongsTo(User, 'uploaded_by')`

---

### 2.12 Notification

In-app bildirim kaydı.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| user_id | `FK → users` | Bildirim alıcısı |
| type | `string` | Bildirim tipi (`story_status_changed`, `task_assigned`, ...) |
| data | `json` | Bildirim içeriği (esnek yapı) |
| read_at | `timestamp(nullable)` | Okunma zamanı (null = okunmamış) |
| created_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(User)`

**Scopes:**
- `scopeUnread()` — `read_at IS NULL`

---

### 2.13 ActivityLog

Proje içi tüm aktivitelerin audit kaydı.

| Attribute | Tip | Açıklama |
|-----------|-----|----------|
| id | `uuid_v7` | Birincil anahtar |
| project_id | `FK → projects` | |
| user_id | `FK → users` | Aksiyonu yapan kullanıcı |
| action | `string` | Aksiyon tipi (`created`, `updated`, `deleted`, `status_changed`) |
| subject_type | `string` | Polymorphic tip |
| subject_id | `uuid_v7` | Polymorphic ID |
| changes | `json(nullable)` | Değişen alanlar (before/after) |
| created_at | `timestamp` | |

**İlişkiler:**
- `belongsTo(Project)`
- `belongsTo(User)`
- `morphTo(subject)` — Herhangi bir entity

---

## 3. Enum Tanımları

### ProjectRole
```
owner      — Proje sahibi, en yüksek yetki
moderator  — Yardımcı yönetici
member     — Standart üye
```

### StoryStatus / TaskStatus / IssueStatus
```
new          — Yeni oluşturulmuş
in_progress  — Üzerinde çalışılıyor
done         — Tamamlanmış
```

### SprintStatus
```
planning  — Henüz başlamadı, hikaye eklenebilir
active    — Devam ediyor
closed    — Tamamlandı
```

### IssueType
```
bug          — Hata
question     — Soru
enhancement  — İyileştirme önerisi
```

### IssuePriority
```
low     — Düşük öncelik
normal  — Normal öncelik
high    — Yüksek öncelik
```

### IssueSeverity
```
wishlist  — İstenen ama zorunlu olmayan
minor     — Küçük etki
critical  — Kritik, acil çözülmeli
```

---

## 4. İlişki Diyagramı (ER — Kısaltılmış)

```
┌──────────┐       ┌───────────────────┐       ┌──────────┐
│  users   │──1:N──│project_memberships│──N:1──│ projects │
└──────────┘       └───────────────────┘       └────┬─────┘
     │                                               │
     │ (assigned_to / created_by)          ┌─────────┼──────────┬────────────┐
     │                                     │         │          │            │
     │                                ┌────▼───┐ ┌───▼────┐ ┌──▼─────┐ ┌───▼────┐
     │                                │ epics  │ │sprints │ │ issues │ │activity│
     │                                └────┬───┘ └───┬────┘ └────────┘ │ _logs  │
     │                                     │         │                  └────────┘
     │                                     │    ┌────▼──────────┐
     │                                     └───►│ user_stories  │◄────────┐
     │                                          └──┬─────┬──────┘         │
     │                                             │     │                │
     │                                        ┌────▼──┐ ┌▼───────────┐   │
     └────────────────────────────────────────►│tasks │ │story_points│   │
                                               └──────┘ └────────────┘   │
                                                                         │
                                          ┌──────────────────────────────┘
                                          │ sprint_scope_changes
                                          └──────────────────────

  attachments (polymorphic) ──► user_stories, tasks, issues
  notifications ──► users
```

---

**Önceki:** [01-ARCHITECTURE_OVERVIEW.md](./01-ARCHITECTURE_OVERVIEW.md)
**Sonraki:** [03-STATE_MACHINE.md](./03-STATE_MACHINE.md)
