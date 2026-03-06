# 05 — RBAC & Permissions

Proje bazlı rol tabanlı erişim kontrolü (Role-Based Access Control), Policy yapısı ve middleware akışı.

**İlişkili Dokümanlar:** [Architecture Overview](./01-ARCHITECTURE_OVERVIEW.md) | [Business Rules](./04-BUSINESS_RULES.md) | [API Design](./07-API_DESIGN.md)

---

## 1. Temel Prensipler

1. **İzinler proje bazlıdır** — Sistem genelinde rol kavramı yoktur (super admin hariç)
2. **Aynı kullanıcı farklı projelerde farklı rollere sahip olabilir**
3. **Yetki kontrolleri Policy ile yapılır** — Controller/Service'te manuel ID karşılaştırması **YASAK**
4. **3 hiyerarşik rol:** Owner > Moderator > Member

---

## 2. Rol Tanımları

### 2.1 Owner (Proje Sahibi)

- Projeyi kuran ve en yetkili kişi
- Diğer rollerin sahip olduğu **tüm yetkilere** sahiptir
- Projeyi silme ve devretme gibi **münhasır** yetkilere sahiptir
- Her projede **tek Owner** bulunur

### 2.2 Moderator (Yardımcı Yönetici)

- Owner tarafından yetkilendirilir
- Epic ve User Story oluşturabilir
- Task oluşturabilir ve başka kullanıcılara atayabilir
- Üye ekleyebilir
- **Yapamadıkları:** Projeyi silme, devretme, rol değiştirme

### 2.3 Member (Üye)

- En kısıtlı rol
- Üye ekleyemez
- User Story oluşturamaz
- Başka kullanıcılara task veremez
- **Sadece kendi task'larında** yetkiye sahiptir
- Issue oluşturabilir ve **kendi issue'larını** düzenleyebilir

---

## 3. Yetki Matrisi

| # | İzin | Owner | Moderator | Member |
|---|------|:-----:|:---------:|:------:|
| P1 | Proje ayarlarını düzenle | ✅ | ❌ | ❌ |
| P2 | Projeyi sil | ✅ | ❌ | ❌ |
| P3 | Projeyi devret (transfer ownership) | ✅ | ❌ | ❌ |
| P4 | Üye ekle | ✅ | ✅ | ❌ |
| P5 | Üye çıkar | ✅ | ✅ | ❌ |
| P6 | Üye rolünü değiştir | ✅ | ❌ | ❌ |
| P7 | Epic oluştur | ✅ | ✅ | ❌ |
| P8 | Epic düzenle | ✅ | ✅ | ❌ |
| P9 | Epic sil | ✅ | ✅ | ❌ |
| P10 | User Story oluştur | ✅ | ✅ | ❌ |
| P11 | User Story düzenle | ✅ | ✅ | ❌ |
| P12 | User Story sil | ✅ | ✅ | ❌ |
| P13 | User Story puanla | ✅ | ✅ | ❌ |
| P14 | Sprint oluştur | ✅ | ✅ | ❌ |
| P15 | Sprint başlat / kapat | ✅ | ✅ | ❌ |
| P16 | Sprint'e story taşı | ✅ | ✅ | ❌ |
| P17 | Task oluştur | ✅ | ✅ | ✅ |
| P18 | Task başkasına ata | ✅ | ✅ | ❌ |
| P19 | Kendi oluşturduğu task'ın durumunu değiştir | ✅ | ✅ | ✅ |
| P20 | Task düzenle (kendi oluşturduğu) | ✅ | ✅ | ✅ |
| P21 | Issue oluştur | ✅ | ✅ | ✅ |
| P22 | Herkesin issue'sunu düzenle | ✅ | ✅ | ❌ |
| P23 | Kendi issue'sunu düzenle | ✅ | ✅ | ✅ |
| P24 | Dosya ekle (kendi) | ✅ | ✅ | ✅ |
| P25 | Dosya sil (kendi) | ✅ | ✅ | ✅ |
| P26 | Dosya sil (herkesinki) | ✅ | ✅ | ❌ |
| P27 | Yorum yaz | ✅ | ✅ | ✅ |
| P28 | Burndown / rapor görüntüle | ✅ | ✅ | ✅ |
| P29 | Proje detaylarını görüntüle | ✅ | ✅ | ✅ |

---

## 4. Policy Implementasyonu

### 4.1 Rol Belirleme Helper

```php
// Her Policy'de kullanılacak ortak yardımcı metot

private function getMemberRole(User $user, Project $project): ?ProjectRole
{
    $membership = $user->projectMemberships()
        ->where('project_id', $project->id)
        ->first();

    return $membership?->role;
}

private function isAtLeast(User $user, Project $project, ProjectRole $minimumRole): bool
{
    $role = $this->getMemberRole($user, $project);

    if ($role === null) return false;

    return $role->rank() >= $minimumRole->rank();
}
```

### 4.2 ProjectRole Enum (Hiyerarşi)

```php
enum ProjectRole: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Member = 'member';

    public function rank(): int
    {
        return match($this) {
            self::Owner     => 3,
            self::Moderator => 2,
            self::Member    => 1,
        };
    }
}
```

### 4.3 Policy Örnekleri

#### ProjectPolicy

```php
class ProjectPolicy
{
    // P1: Proje ayarlarını düzenle
    public function update(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Owner);
    }

    // P2: Projeyi sil
    public function delete(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Owner);
    }

    // P29: Proje görüntüle
    public function view(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }
}
```

#### UserStoryPolicy

```php
class UserStoryPolicy
{
    // P10: Story oluştur
    public function create(User $user, Project $project): bool
    {
        return $this->isAtLeast($user, $project, ProjectRole::Moderator);
    }

    // P11: Story düzenle
    public function update(User $user, UserStory $story): bool
    {
        return $this->isAtLeast($user, $story->project, ProjectRole::Moderator);
    }
}
```

#### TaskPolicy

```php
class TaskPolicy
{
    // P17: Task oluştur — Tüm proje üyeleri
    public function create(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }

    // P19: Kendi oluşturduğu task'ın durumunu değiştir
    public function changeStatus(User $user, Task $task): bool
    {
        $role = $this->getMemberRole($user, $task->userStory->project);

        if ($role === null) return false;

        // Owner ve Moderator her task'ı değiştirebilir
        if ($role->rank() >= ProjectRole::Moderator->rank()) return true;

        // Member sadece kendi oluşturduğu task'ın durumunu değiştirebilir
        return $task->created_by === $user->id;
    }

    // P20: Task düzenle (kendi oluşturduğu veya Moderator+)
    public function update(User $user, Task $task): bool
    {
        $role = $this->getMemberRole($user, $task->userStory->project);

        if ($role === null) return false;

        // Owner ve Moderator her task'ı düzenleyebilir
        if ($role->rank() >= ProjectRole::Moderator->rank()) return true;

        // Member sadece kendi oluşturduğu task'ı düzenleyebilir
        return $task->created_by === $user->id;
    }
}
```

#### IssuePolicy

```php
class IssuePolicy
{
    // P21: Issue oluştur — herkes
    public function create(User $user, Project $project): bool
    {
        return $this->getMemberRole($user, $project) !== null;
    }

    // P22 + P23: Issue düzenle
    public function update(User $user, Issue $issue): bool
    {
        $role = $this->getMemberRole($user, $issue->project);

        if ($role === null) return false;

        // Owner ve Moderator herkesinki
        if ($role->rank() >= ProjectRole::Moderator->rank()) return true;

        // Member sadece kendi issue'su
        return $issue->created_by === $user->id;
    }
}
```

---

## 5. Middleware Akışı

### 5.1 İstek İşleme Zinciri

```
HTTP Request
    │
    ▼
┌── auth middleware ──────────────────────────┐
│   Session doğrulama                         │
│   Başarısız → 401 Unauthorized              │
└─────────────┬──────────────────────────────┘
              │
              ▼
┌── EnsureProjectMember middleware ───────────┐
│   URL'den project slug al                   │
│   Kullanıcı bu projenin üyesi mi?           │
│   Başarısız → 403 Forbidden                 │
└─────────────┬──────────────────────────────┘
              │
              ▼
┌── Policy (Controller / FormRequest) ────────┐
│   $this->authorize('update', $story)        │
│   Kullanıcının rolü bu eyleme izin veriyor  │
│   mu?                                       │
│   Başarısız → 403 Forbidden                 │
└─────────────┬──────────────────────────────┘
              │
              ▼
        Controller → Service → Action
```

### 5.2 EnsureProjectMember Middleware

```php
class EnsureProjectMember
{
    public function handle(Request $request, Closure $next)
    {
        $project = $request->route('project'); // Route model binding

        if (!$project) {
            return $next($request);
        }

        $isMember = $request->user()
            ->projectMemberships()
            ->where('project_id', $project->id)
            ->exists();

        if (!$isMember && !$request->user()->is_super_admin) {
            abort(403, 'You are not a member of this project.');
        }

        return $next($request);
    }
}
```

### 5.3 Route Tanımı

```php
Route::middleware(['auth', 'project.member'])->group(function () {
    Route::prefix('projects/{project:slug}')->group(function () {
        Route::apiResource('stories', UserStoryController::class);
        Route::apiResource('epics', EpicController::class);
        Route::apiResource('sprints', SprintController::class);
        Route::apiResource('issues', IssueController::class);
        // ...
    });
});
```

---

## 6. Super Admin

- `users.is_super_admin = true` olan kullanıcılar **tüm projelere** erişebilir
- Super Admin kontrolü middleware ve Policy seviyesinde yapılır
- Super Admin proje üyesi olmasa bile projeye erişebilir

```php
// Policy'lerde before() metodu ile
public function before(User $user, string $ability): ?bool
{
    if ($user->is_super_admin) {
        return true; // Tüm kontrolleri bypass et
    }

    return null; // Normal akışa devam et
}
```

---

## 7. HTTP Status Kodları

| Durum | Kod | Açıklama |
|-------|-----|----------|
| Authenticate olmamış | `401` | Auth middleware tarafından |
| Proje üyesi değil | `403` | EnsureProjectMember middleware |
| Rol yetersiz | `403` | Policy tarafından |
| Kaynak bulunamadı | `404` | Route model binding |

---

**Önceki:** [04-BUSINESS_RULES.md](./04-BUSINESS_RULES.md)
**Sonraki:** [06-DATABASE_SCHEMA.md](./06-DATABASE_SCHEMA.md)
