# 06 — Database Schema

Tablo tanımları, migration planı, indeks stratejisi, constraint'ler ve JSONB custom fields yapısı.

**İlişkili Dokümanlar:** [Domain Model](./02-DOMAIN_MODEL.md) | [Business Rules](./04-BUSINESS_RULES.md)

---

## 1. Genel Kurallar

- **Primary Key:** Tüm tablolarda `UUID v7` kullanılır (sıralı, unique, URL-safe)
- **Timestamps:** `created_at` ve `updated_at` her tabloda bulunur (Eloquent default)
- **Soft Delete:** Sadece `projects` tablosunda (cascade silme yerine)
- **Foreign Key:** Tüm ilişkiler FK constraint ile korunur
- **Index:** Sorgulanan FK'lar, filtreleme alanları ve unique constraint'ler index'lenir
- **Enum:** PHP Enum → DB `string` olarak saklanır (Laravel cast ile)
- **JSON/JSONB:** Dev'de JSON (SQLite), Prod'da JSONB (PostgreSQL)

---

## 2. Migration Sıralaması

Migration'lar tek klasörde, tarih sırasıyla çalışır. FK bağımlılıkları nedeniyle sıralama kritiktir:

```
01. create_users_table
02. create_sessions_table
03. create_projects_table
04. create_project_memberships_table
05. create_epics_table
06. create_sprints_table
07. create_user_stories_table
08. create_story_points_table
09. create_sprint_scope_changes_table
10. create_tasks_table
11. create_issues_table
12. create_attachments_table
13. create_notifications_table
14. create_activity_logs_table
```

---

## 3. Tablo Tanımları

### 3.1 users

```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->string('avatar')->nullable();
    $table->boolean('is_super_admin')->default(false);
    $table->rememberToken();
    $table->timestamps();
});
```

| Sütun | Tip | Constraint | Açıklama |
|-------|-----|------------|----------|
| id | `uuid_v7` | PK | |
| name | `varchar(255)` | NOT NULL | |
| email | `varchar(255)` | UNIQUE, NOT NULL | Login tanımlayıcı |
| email_verified_at | `timestamp` | NULLABLE | |
| password | `varchar(255)` | NOT NULL | Bcrypt hash |
| avatar | `varchar(255)` | NULLABLE | MinIO dosya yolu |
| is_super_admin | `boolean` | DEFAULT false | Sistem yöneticisi mi |

---

### 3.2 sessions

Laravel default session tablosu (Session-based auth için).

```php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignUuid('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

---

### 3.3 projects

```php
Schema::create('projects', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
    $table->json('settings')->nullable(); // JSONB in PostgreSQL
    $table->timestamps();
    $table->softDeletes();

    $table->index('owner_id');
});
```

**settings JSON Yapısı:**
```json
{
    "modules": { "scrum": true, "issues": true },
    "estimation_roles": ["UX", "Design", "Frontend", "Backend"],
    "max_members": 50,
    "default_story_status": "new",
    "default_issue_priority": "normal"
}
```

---

### 3.4 project_memberships

```php
Schema::create('project_memberships', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('role'); // Enum: owner, moderator, member
    $table->timestamps();

    $table->unique(['project_id', 'user_id']);
    $table->index('user_id');
});
```

| Constraint | Açıklama |
|------------|----------|
| `UNIQUE(project_id, user_id)` | Bir kullanıcı bir projede tek üyelik |
| FK → projects (CASCADE) | Proje silinince üyelik de silinir |
| FK → users (CASCADE) | Kullanıcı silinince üyelik de silinir |

---

### 3.5 epics

```php
Schema::create('epics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('color', 7)->default('#6366F1'); // Hex color
    $table->string('status')->default('new'); // Enum: new, in_progress, done
    $table->unsignedInteger('order')->default(0);
    $table->timestamps();

    $table->index(['project_id', 'status']);
});
```

---

### 3.6 sprints

```php
Schema::create('sprints', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->date('start_date');
    $table->date('end_date');
    $table->string('status')->default('planning'); // Enum: planning, active, closed
    $table->timestamps();

    $table->index(['project_id', 'status']);
});
```

---

### 3.7 user_stories

```php
Schema::create('user_stories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('epic_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignUuid('sprint_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('status')->default('new'); // Enum: new, in_progress, done
    $table->decimal('total_points', 8, 2)->default(0);
    $table->json('custom_fields')->nullable();
    $table->unsignedInteger('order')->default(0);
    $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();

    $table->index(['project_id', 'status']);
    $table->index('sprint_id');
    $table->index('epic_id');
});
```

| FK Davranışı | Açıklama |
|-------------|----------|
| `epic_id` → NULL ON DELETE | Epic silinirse story epic'siz kalır (Backlog'a aittir) |
| `sprint_id` → NULL ON DELETE | Sprint silinirse story Backlog'a döner |
| `project_id` → CASCADE | Proje silinirse story'ler de silinir |

---

### 3.8 story_points

```php
Schema::create('story_points', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_story_id')->constrained()->cascadeOnDelete();
    $table->string('role_name', 50); // UX, Design, Frontend, Backend
    $table->decimal('points', 5, 2);

    $table->unique(['user_story_id', 'role_name']); // Her rol bir kez puanlayabilir
});
```

---

### 3.9 sprint_scope_changes

```php
Schema::create('sprint_scope_changes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('sprint_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('user_story_id')->constrained()->cascadeOnDelete();
    $table->string('change_type'); // Enum: added, removed
    $table->timestamp('changed_at');
    $table->foreignUuid('changed_by')->constrained('users')->cascadeOnDelete();

    $table->index(['sprint_id', 'changed_at']);
});
```

---

### 3.10 tasks

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_story_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('status')->default('new'); // Enum: new, in_progress, done
    $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();

    $table->index('user_story_id');
    $table->index(['assigned_to', 'status']);
});
```

---

### 3.11 issues

```php
Schema::create('issues', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('type');        // Enum: bug, question, enhancement
    $table->string('priority');    // Enum: low, normal, high
    $table->string('severity');    // Enum: wishlist, minor, critical
    $table->string('status')->default('new'); // Enum: new, in_progress, done
    $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();

    $table->index(['project_id', 'status']);
    $table->index('assigned_to');
});
```

---

### 3.12 attachments

```php
Schema::create('attachments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuidMorphs('attachable'); // attachable_type + attachable_id
    $table->string('filename');
    $table->string('path', 500);
    $table->string('mime_type', 100);
    $table->unsignedBigInteger('size'); // bytes
    $table->foreignUuid('uploaded_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();

    // uuidMorphs zaten attachable_type + attachable_id üzerine index oluşturur
});
```

---

### 3.13 notifications

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // Bildirim tipi string
    $table->json('data');
    $table->timestamp('read_at')->nullable();
    $table->timestamp('created_at');

    $table->index(['user_id', 'read_at']);
});
```

---

### 3.14 activity_logs

```php
Schema::create('activity_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('action'); // created, updated, deleted, status_changed
    $table->string('subject_type');
    $table->uuid('subject_id');
    $table->json('changes')->nullable(); // {"before": {...}, "after": {...}}
    $table->timestamp('created_at');

    $table->index(['project_id', 'created_at']);
    $table->index(['subject_type', 'subject_id']);
});
```

---

## 4. İndeks Stratejisi Özeti

| Tablo | İndeks | Tip | Gerekçe |
|-------|--------|-----|---------|
| users | email | UNIQUE | Login sorgusu |
| projects | slug | UNIQUE | URL erişim |
| projects | owner_id | INDEX | Owner sorgusu |
| project_memberships | (project_id, user_id) | UNIQUE | Tekil üyelik |
| project_memberships | user_id | INDEX | "Üye olduğum projeler" |
| epics | (project_id, status) | INDEX | Proje epic listesi |
| sprints | (project_id, status) | INDEX | Aktif sprint sorgusu |
| user_stories | (project_id, status) | INDEX | Backlog/board filtresi |
| user_stories | sprint_id | INDEX | Sprint story listesi |
| user_stories | epic_id | INDEX | Epic story listesi |
| story_points | (user_story_id, role_name) | UNIQUE | Her rol tek puan |
| sprint_scope_changes | (sprint_id, changed_at) | INDEX | Burndown hesaplama |
| tasks | user_story_id | INDEX | Story task listesi |
| tasks | (assigned_to, status) | INDEX | "Bana atanmış task'lar" |
| issues | (project_id, status) | INDEX | Issue listesi |
| issues | assigned_to | INDEX | "Bana atanmış issue'lar" |
| notifications | (user_id, read_at) | INDEX | Okunmamış bildirimler |
| activity_logs | (project_id, created_at) | INDEX | Proje aktivite geçmişi |
| activity_logs | (subject_type, subject_id) | INDEX | Entity aktivitesi |

---

## 5. SQLite vs PostgreSQL Farklılıkları

| Özellik | SQLite (Dev) | PostgreSQL (Prod) |
|---------|-------------|-------------------|
| JSON sütunlar | `json` (text olarak saklanır) | `jsonb` (binary, indexlenebilir) |
| UUID v7 | String olarak saklanır | String olarak saklanır |
| Concurrent write | Single-writer lock | MVCC, paralel yazma |
| Full-text search | FTS5 extension | Native `tsvector` |
| LISTEN/NOTIFY | Desteklenmiyor | Destekleniyor (real-time) |

**Geçiş stratejisi:** Laravel DB abstraction tüm farklılıkları absorbe eder. Kod değişikliği gerekmez, sadece `.env` config değişir.

---

## 6. Seed Verisi

```
DatabaseSeeder:
├── UserSeeder         → Admin + test kullanıcıları
├── ProjectSeeder      → 2-3 örnek proje
├── MembershipSeeder   → Her projede 3 roldeki kullanıcılar
├── EpicSeeder         → Her projede 2-3 epic
├── SprintSeeder       → Her projede 1 active + 1 closed sprint
├── UserStorySeeder    → Backlog + Sprint'te story'ler
├── StoryPointSeeder   → Puanlanmış story'ler
├── TaskSeeder         → Story'lere bağlı task'lar
└── IssueSeeder        → Farklı tip/öncelik/şiddette issue'lar
```

---

**Önceki:** [05-RBAC_PERMISSIONS.md](./05-RBAC_PERMISSIONS.md)
**Sonraki:** [07-API_DESIGN.md](./07-API_DESIGN.md)
