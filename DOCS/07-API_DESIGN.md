# 07 — API Design

REST API endpoint tanımları, request/response formatları, WebSocket channel yapısı ve HTTP status kod kullanımı.

**İlişkili Dokümanlar:** [RBAC](./05-RBAC_PERMISSIONS.md) | [Domain Model](./02-DOMAIN_MODEL.md) | [Coding Standards](./14-CODING_STANDARDS.md)

---

## 1. Genel Kurallar

- **URL format:** `kebab-case`, çoğul isimler (`/projects`, `/stories`)
- **Auth:** Session-based (web) + Sanctum SPA mode (API — cookie auth)
- **Response format:** JSON (`application/json`)
- **Pagination:** `?page=1&per_page=20` — Laravel default pagination
- **Filtreleme:** Query string parametreleri (`?status=new&priority=high`)
- **Sıralama:** `?sort=created_at&order=desc`
- **API Prefix:** `/api/` (Sanctum route'ları `/api/auth/` altında)
- **Proje bazlı route'lar:** `/api/projects/{project:slug}/...`

---

## 2. HTTP Status Kodları

| Kod | Kullanım | Açıklama |
|-----|----------|----------|
| `200` | OK | Başarılı GET, PUT |
| `201` | Created | Başarılı POST (kaynak oluşturma) |
| `204` | No Content | Başarılı DELETE |
| `401` | Unauthorized | Authenticate olmamış |
| `403` | Forbidden | Yetkisiz (Policy reject) |
| `404` | Not Found | Kaynak bulunamadı |
| `422` | Unprocessable Entity | Validation hatası, iş kuralı ihlali |
| `429` | Too Many Requests | Rate limiting |
| `500` | Internal Server Error | Beklenmeyen hata |

---

## 3. Authentication Endpoints

### POST /api/auth/register

Yeni kullanıcı kaydı.

**Request:**
```json
{
    "name": "Selim Dev",
    "email": "selim@example.com",
    "password": "securePassword123",
    "password_confirmation": "securePassword123"
}
```

**Response:** `201 Created`
```json
{
    "data": {
        "id": "01HYX...",
        "name": "Selim Dev",
        "email": "selim@example.com",
        "created_at": "2026-03-02T10:00:00Z"
    }
}
```

### POST /api/auth/login

Session oluştur.

**Request:**
```json
{
    "email": "selim@example.com",
    "password": "securePassword123"
}
```

**Response:** `200 OK`
```json
{
    "data": {
        "id": "01HYX...",
        "name": "Selim Dev",
        "email": "selim@example.com"
    }
}
```

### POST /api/auth/logout

Session sonlandır.

**Response:** `204 No Content`

### GET /api/auth/me

Mevcut kullanıcı bilgisi.

**Response:** `200 OK`
```json
{
    "data": {
        "id": "01HYX...",
        "name": "Selim Dev",
        "email": "selim@example.com",
        "avatar": "https://minio.example.com/avatars/01HYX.jpg",
        "is_super_admin": false
    }
}
```

---

## 4. Project Endpoints

### GET /api/projects

Kullanıcının üye olduğu projeleri listeler.

**Query params:** `?search=keyword&sort=created_at&order=desc&page=1&per_page=20`

**Response:** `200 OK`
```json
{
    "data": [
        {
            "id": "01HYX...",
            "name": "Taiga Klonu",
            "slug": "taiga-klonu",
            "description": "Proje yönetim platformu",
            "owner": { "id": "...", "name": "Selim Dev" },
            "member_count": 5,
            "my_role": "owner",
            "created_at": "2026-03-02T10:00:00Z"
        }
    ],
    "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 54 }
}
```

### POST /api/projects

**Auth:** `middleware('auth')`
**Request:**
```json
{
    "name": "Yeni Proje",
    "description": "Proje açıklaması"
}
```

**Response:** `201 Created` — Project resource + otomatik owner üyelik

### GET /api/projects/{slug}

**Auth:** `middleware('auth', 'project.member')`

**Response:** `200 OK`
```json
{
    "data": {
        "id": "01HYX...",
        "name": "Taiga Klonu",
        "slug": "taiga-klonu",
        "description": "...",
        "settings": { "modules": { "scrum": true }, "estimation_roles": ["UX", "Frontend"] },
        "owner": { "id": "...", "name": "Selim Dev" },
        "member_count": 5,
        "my_role": "owner",
        "created_at": "2026-03-02T10:00:00Z"
    }
}
```

### PUT /api/projects/{slug}

**Policy:** `ProjectPolicy@update` (Owner only)

### DELETE /api/projects/{slug}

**Policy:** `ProjectPolicy@delete` (Owner only)

**Response:** `204 No Content`

---

## 5. Member Endpoints

### GET /api/projects/{slug}/members

**Response:** `200 OK`
```json
{
    "data": [
        {
            "id": "01HYX...",
            "user": { "id": "...", "name": "Selim Dev", "email": "selim@example.com", "avatar": null },
            "role": "owner",
            "joined_at": "2026-03-02T10:00:00Z"
        }
    ]
}
```

### POST /api/projects/{slug}/members

**Policy:** Owner + Moderator (P4)

**Request:**
```json
{
    "email": "newmember@example.com",
    "role": "member"
}
```

**Response:** `201 Created`

**Hata durumları:** `422` (zaten üye, email bulunamadı), `403` (yetkisiz)

### DELETE /api/projects/{slug}/members/{userId}

**Policy:** Owner + Moderator (P5). Owner çıkarılamaz (BR-14).

**Response:** `204 No Content`

---

## 6. Epic Endpoints

Base: `/api/projects/{slug}/epics`

| Method | Path | Policy | Açıklama |
|--------|------|--------|----------|
| GET | `/` | Member+ | Epic listesi (tamamlanma yüzdesiyle) |
| POST | `/` | Moderator+ (P7) | Epic oluştur |
| GET | `/{id}` | Member+ | Epic detay + story listesi |
| PUT | `/{id}` | Moderator+ (P8) | Epic güncelle |
| DELETE | `/{id}` | Moderator+ (P9) | Epic sil |

**GET Response örnek:**
```json
{
    "data": {
        "id": "01HYX...",
        "title": "Kullanıcı Yönetimi",
        "description": "...",
        "color": "#6366F1",
        "status": "in_progress",
        "completion_percentage": 67,
        "stories_count": 6,
        "stories_done_count": 4
    }
}
```

---

## 7. User Story Endpoints

Base: `/api/projects/{slug}/stories`

| Method | Path | Policy | Açıklama |
|--------|------|--------|----------|
| GET | `/` | Member+ | Story listesi (filtre: backlog, sprint, epic, status) |
| POST | `/` | Moderator+ (P10) | Story oluştur |
| GET | `/{id}` | Member+ | Story detay + task'lar + point'ler |
| PUT | `/{id}` | Moderator+ (P11) | Story güncelle |
| DELETE | `/{id}` | Moderator+ (P12) | Story sil |
| POST | `/{id}/move-to-sprint` | Moderator+ (P16) | Sprint'e taşı |
| PUT | `/{id}/estimate` | Moderator+ (P13) | Puanla |
| PUT | `/{id}/status` | Moderator+ | Durum değiştir |
| PUT | `/{id}/reorder` | Moderator+ | Sıralama değiştir |

**GET filtre örnekleri:**
```
?backlog=true               → sprint_id IS NULL
?sprint_id=01HYX...        → Belirli sprint'teki
?epic_id=01HYX...          → Belirli epic'teki
?status=new,in_progress    → Duruma göre
```

**POST /move-to-sprint Request:**
```json
{
    "sprint_id": "01HYX..."
}
```

**PUT /estimate Request:**
```json
{
    "points": [
        { "role_name": "UX", "points": 3 },
        { "role_name": "Frontend", "points": 8 },
        { "role_name": "Backend", "points": 5 }
    ]
}
```

---

## 8. Sprint Endpoints

Base: `/api/projects/{slug}/sprints`

| Method | Path | Policy | Açıklama |
|--------|------|--------|----------|
| GET | `/` | Member+ | Sprint listesi |
| POST | `/` | Moderator+ (P14) | Sprint oluştur |
| GET | `/{id}` | Member+ | Sprint detay + story'ler |
| PUT | `/{id}` | Moderator+ | Sprint güncelle |
| DELETE | `/{id}` | Moderator+ | Sprint sil (sadece planning) |
| POST | `/{id}/start` | Moderator+ (P15) | Sprint başlat |
| POST | `/{id}/close` | Moderator+ (P15) | Sprint kapat |

**POST /start Response:** `200 OK` veya `422` (zaten aktif sprint var — BR-05)

**POST /close Response:** `200 OK` — Tamamlanmamış story'ler backlog'a döner (BR-08)

---

## 9. Task Endpoints

| Method | Path | Policy | Açıklama |
|--------|------|--------|----------|
| GET | `/api/stories/{storyId}/tasks` | Member+ | Story'nin task listesi |
| POST | `/api/stories/{storyId}/tasks` | Moderator+ (P17) | Task oluştur |
| PUT | `/api/tasks/{id}` | Moderator+ (P20) | Task güncelle |
| PUT | `/api/tasks/{id}/status` | Assigned user (P19) | Durum değiştir |
| PUT | `/api/tasks/{id}/assign` | Moderator+ (P18) | Kullanıcıya ata |
| DELETE | `/api/tasks/{id}` | Moderator+ | Task sil |

**PUT /status Request:**
```json
{
    "status": "in_progress"
}
```

**Hata:** `422` — Geçersiz geçiş (State Machine), atanmamış task başlatma (BR-16)

---

## 10. Issue Endpoints

Base: `/api/projects/{slug}/issues`

| Method | Path | Policy | Açıklama |
|--------|------|--------|----------|
| GET | `/` | Member+ | Issue listesi (filtre: type, priority, severity, status) |
| POST | `/` | Member+ (P21) | Issue oluştur |
| GET | `/{id}` | Member+ | Issue detay |
| PUT | `/{id}` | Owner/Mod veya kendi (P22/P23) | Issue güncelle |
| DELETE | `/{id}` | Moderator+ | Issue sil |

**GET filtre örnekleri:**
```
?type=bug
?priority=high
?severity=critical
?status=new,in_progress
?assigned_to=me
```

---

## 11. Attachment Endpoints

| Method | Path | Policy | Açıklama |
|--------|------|--------|----------|
| POST | `/api/attachments` | Member+ (P24) | Dosya yükle |
| DELETE | `/api/attachments/{id}` | Kendi (P25) veya Mod+ (P26) | Dosya sil |

**POST Request (multipart/form-data):**
```
attachable_type: user_story | task | issue
attachable_id:   01HYX...
file:            (binary)
```

**Response:** `201 Created`
```json
{
    "data": {
        "id": "01HYX...",
        "filename": "screenshot.png",
        "mime_type": "image/png",
        "size": 245680,
        "url": "https://minio.example.com/attachments/01HYX/screenshot.png",
        "uploaded_by": { "id": "...", "name": "Selim Dev" },
        "created_at": "2026-03-02T10:00:00Z"
    }
}
```

---

## 12. Notification Endpoints

| Method | Path | Açıklama |
|--------|------|----------|
| GET | `/api/notifications` | Okunmamış bildirimler |
| POST | `/api/notifications/mark-read` | Okundu işaretle |
| POST | `/api/notifications/mark-all-read` | Tümünü okundu işaretle |

**GET Response:**
```json
{
    "data": [
        {
            "id": "01HYX...",
            "type": "story_status_changed",
            "data": {
                "story_id": "01HYX...",
                "story_title": "Login Sayfası",
                "old_status": "in_progress",
                "new_status": "done",
                "changed_by": "Selim Dev"
            },
            "read_at": null,
            "created_at": "2026-03-02T10:00:00Z"
        }
    ],
    "meta": { "unread_count": 12 }
}
```

---

## 13. Analytics Endpoints

| Method | Path | Açıklama |
|--------|------|----------|
| GET | `/api/projects/{slug}/sprints/{id}/burndown` | Burndown chart verileri |
| GET | `/api/projects/{slug}/velocity` | Sprint velocity |

**GET /burndown Response:**
```json
{
    "data": {
        "sprint": { "name": "Sprint 5", "start_date": "2026-03-01", "end_date": "2026-03-14" },
        "total_points": 42,
        "ideal_line": [42, 39, 36, 33, 30, 27, 24, 21, 18, 15, 12, 9, 6, 3, 0],
        "actual_line": [42, 42, 38, 35, 35, 30, 28, null, null, null, null, null, null, null, null],
        "scope_changes": [
            { "date": "2026-03-05", "type": "added", "points_delta": 5 }
        ]
    }
}
```

**GET /velocity Response:**
```json
{
    "data": {
        "sprints": [
            { "name": "Sprint 3", "completed_points": 34 },
            { "name": "Sprint 4", "completed_points": 42 },
            { "name": "Sprint 5", "completed_points": 38 }
        ],
        "average_velocity": 38
    }
}
```

---

## 14. WebSocket Channels

| Channel | Event | Payload | Açıklama |
|---------|-------|---------|----------|
| `private-project.{id}` | `StoryStatusChanged` | `{story, oldStatus, newStatus}` | Board güncelle |
| `private-project.{id}` | `StoryCreated` | `{story}` | Backlog'a ekle |
| `private-project.{id}` | `TaskStatusChanged` | `{task, oldStatus, newStatus}` | Task board güncelle |
| `private-project.{id}` | `MemberAdded` | `{member}` | Üye listesi güncelle |
| `private-project.{id}` | `SprintStarted` | `{sprint}` | Sprint başladı |
| `private-user.{id}` | `NotificationReceived` | `{notification}` | Bildirim zili güncelle |

**Channel Authorization (channels.php):**
```php
Broadcast::channel('project.{projectId}', function (User $user, string $projectId) {
    return $user->projectMemberships()
        ->where('project_id', $projectId)
        ->exists();
});

Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
```

---

## 15. Error Response Formatı

Tüm hata response'ları tutarlı format:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "name": ["The name field must not be greater than 255 characters."]
    }
}
```

Business rule ihlali:
```json
{
    "message": "There is already an active sprint in this project.",
    "error_code": "ACTIVE_SPRINT_EXISTS"
}
```

---

**Önceki:** [06-DATABASE_SCHEMA.md](./06-DATABASE_SCHEMA.md)
**Sonraki:** [08-PROJECT_STRUCTURE.md](./08-PROJECT_STRUCTURE.md)
