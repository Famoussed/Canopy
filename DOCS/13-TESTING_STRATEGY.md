# 13 — Testing Strategy

TDD yaklaşımı, test kategorileri, zorunlu test senaryoları, test konvansiyonları ve CI entegrasyonu.

**İlişkili Dokümanlar:** [Business Rules](./04-BUSINESS_RULES.md) | [RBAC Permissions](./05-RBAC_PERMISSIONS.md) | [Coding Standards](./14-CODING_STANDARDS.md)

---

## 1. TDD Yaklaşımı

**Red → Green → Refactor** döngüsü uygulanır.

```
1. RED    → Başarısız test yaz (beklenen davranışı tanımla)
2. GREEN  → Testi geçirecek minimum kodu yaz
3. REFACTOR → Kodu iyileştir, test hâlâ geçiyor olmalı
```

### Neden TDD?

- Business rule'lar karmaşık → test ile davranış garanti altına alınır
- State machine geçişleri → edge case'ler test ile yakalanır
- RBAC kuralları → her rol kombinasyonu test edilir
- Refactoring güvenliği → testler kırılganlığı önler

---

## 2. Test Dizin Yapısı

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── RegisterTest.php
│   │   └── LoginTest.php
│   ├── Project/
│   │   ├── ProjectCrudTest.php
│   │   └── MembershipTest.php
│   ├── Scrum/
│   │   ├── EpicCrudTest.php
│   │   ├── UserStoryCrudTest.php
│   │   ├── UserStoryStatusTest.php
│   │   ├── SprintWorkflowTest.php
│   │   ├── TaskCrudTest.php
│   │   ├── TaskStatusTest.php
│   │   └── BacklogReorderTest.php
│   ├── Issue/
│   │   ├── IssueCrudTest.php
│   │   └── IssueStatusTest.php
│   └── Rbac/
│       ├── ProjectPolicyTest.php
│       ├── UserStoryPolicyTest.php
│       ├── TaskPolicyTest.php
│       └── IssuePolicyTest.php
│
├── Unit/
│   ├── Actions/
│   │   ├── ChangeStoryStatusActionTest.php
│   │   ├── ChangeTaskStatusActionTest.php
│   │   ├── ChangeIssueStatusActionTest.php
│   │   ├── StartSprintActionTest.php
│   │   ├── CloseSprintActionTest.php
│   │   ├── DetectScopeChangeActionTest.php
│   │   ├── CalculateEpicCompletionActionTest.php
│   │   ├── CalculateBurndownActionTest.php
│   │   └── CalculateVelocityActionTest.php
│   ├── Services/
│   │   ├── SprintServiceTest.php
│   │   ├── UserStoryServiceTest.php
│   │   └── NotificationServiceTest.php
│   └── Models/
│       ├── StateMachineTraitTest.php
│       └── ProjectRoleEnumTest.php
│
└── Livewire/
    ├── SprintBoardTest.php
    ├── BacklogTest.php
    ├── IssueListTest.php
    ├── NotificationBellTest.php
    └── MemberManagerTest.php
```

---

## 3. Test Kategorileri ve Hedefler

| Kategori | Konum | Hedef | Minimum |
|----------|-------|-------|---------|
| Feature Tests | `tests/Feature/` | API endpoint → DB arası tam akış | 20+ test |
| Unit Tests | `tests/Unit/` | Action/Service izole logic | 10+ test |
| Livewire Tests | `tests/Livewire/` | Component render + interaction | 10+ test |
| Policy Tests | `tests/Feature/Rbac/` | Her rol × her permission | 10+ test |
| **Toplam** | | | **50+ test** |

---

## 4. Zorunlu Test Senaryoları

### 4.1 Feature Tests (API → DB)

| # | Test | Doğrulama |
|---|------|-----------|
| F-01 | Kullanıcı kaydı | Hash'li password, session oluşturma |
| F-02 | Giriş / Çıkış | Session yaratma/silme, 401 kontrol |
| F-03 | Proje CRUD | Oluşturma → otomatik owner üyelik |
| F-04 | Üye ekleme/çıkarma | Duplicate kontrol, owner koruması |
| F-05 | User Story oluşturma | Proje ilişkisi, varsayılan status='new' |
| F-06 | Story status geçişi | new→in_progress→done, yasak geçiş red |
| F-07 | Sprint başlatma | Aktif sprint varken ikinci başlatma engeli |
| F-08 | Sprint kapatma | Bitmemiş story'ler backlog'a dönüşü |
| F-09 | Task oluşturma + atama | assigned_to zorunluluğu (status geçişinde) |
| F-10 | Issue CRUD + status | Oluşturma, güncelleme, status değişimi |
| F-11 | Dosya yükleme | MinIO'ya yazma, Attachment kaydı |
| F-12 | Dosya silme | MinIO'dan silme, DB kaydı temizleme |
| F-13 | Bildirim oluşturma | Event → Listener → Notification kaydı |
| F-14 | Bildirim okundu | Tek/toplu okundu işareti |
| F-15 | Burndown endpoint | Sprint verileri ile doğru hesaplama |
| F-16 | Velocity endpoint | Kapalı sprint verilerinden ortalama |
| F-17 | Backlog sıralama | position güncelleme, çakışma kontrolü |
| F-18 | Epic tamamlanma % | Alt story'ler done olunca yüzde hesabı |
| F-19 | Scope change tespiti | Sprint'e story ekleme/çıkarma kaydı |
| F-20 | Yetkisiz erişim red | 403 response, başka projenin verisi |

### 4.2 Unit Tests (Action/Service izole)

| # | Test | Doğrulama |
|---|------|-----------|
| U-01 | ChangeStoryStatusAction – geçerli geçiş | new→in_progress başarılı |
| U-02 | ChangeStoryStatusAction – yasak geçiş | new→done exception fırlatır |
| U-03 | ChangeTaskStatusAction – assigned_to kontrolü | Atanmamış task in_progress'e geçemez |
| U-04 | StartSprintAction – aktif sprint kontrolü | ActiveSprintAlreadyExistsException |
| U-05 | CloseSprintAction – bitmemiş story'ler | Story'ler backlog'a döner, sprint_id null |
| U-06 | DetectScopeChangeAction | SprintScopeChange kaydı oluşturulur |
| U-07 | CalculateEpicCompletionAction | Done/Total × 100 formülü kontrolü |
| U-08 | CalculateBurndownAction – ideal çizgi | Doğrusal azalma doğrulaması |
| U-09 | CalculateBurndownAction – scope change | Scope ekleme actual'ı artırır |
| U-10 | CalculateVelocityAction | N sprint ortalaması doğrulaması |
| U-11 | ProjectRole enum – hiyerarşi | Owner > Moderator > Member rank |
| U-12 | HasStateMachine trait – geçiş kuralları | allowedTransitions() doğrulaması |

### 4.3 Livewire Tests (Component)

| # | Test | Doğrulama |
|---|------|-----------|
| L-01 | SprintBoard render | Sprint story'leri doğru sütunlarda |
| L-02 | SprintBoard – drag & drop status | Status değişimi service çağrısı |
| L-03 | Backlog – sıralama | Drag sonrası position güncelleme |
| L-04 | Backlog – sprint'e taşıma | Story sprint'e atanır |
| L-05 | IssueList – filtreleme | Status/priority filtreleri çalışır |
| L-06 | IssueList – oluşturma | Modal açılır, form gönderilir |
| L-07 | NotificationBell – sayaç | Yeni bildirimde sayaç artışı |
| L-08 | NotificationBell – okundu | Tıklamada read_at güncellenir |
| L-09 | MemberManager – ekleme | Yeni üye ekleme flow |
| L-10 | MemberManager – rol değiştirme | Moderator ↔ Member |

### 4.4 Policy Tests (RBAC)

| # | Test | Doğrulama |
|---|------|-----------|
| P-01 | Owner tüm işlemler | Her yetki true döner |
| P-02 | Moderator kısıtlamaları | Proje silme/transfer false |
| P-03 | Member kısıtlamaları | Sprint yönetimi, üye yönetimi false |
| P-04 | Üye olmayan erişim | Proje ve alt entity'lere erişim engeli |
| P-05 | Member kendi task'ını düzenler | true |
| P-06 | Member başkasının task'ını düzenler | false |
| P-07 | Moderator herkesin task'ını düzenler | true |
| P-08 | Member issue priority ayarı | false (sadece Moderator+) |
| P-09 | Owner transfer yetkisi | true (sadece Owner) |
| P-10 | EnsureProjectMember middleware | Üye olmayan → 403 |

---

## 5. Test Konvansiyonları

### 5.1 İsimlendirme

```php
// Pattern: test_{action}_{condition}_{expectedResult}

// ✅ Doğru
test('it creates a user story with default new status')
test('it prevents new to done status transition')
test('owner can delete project')
test('member cannot start sprint')

// ❌ Yanlış
test('test1')
test('story test')
test('it works')
```

### 5.2 AAA Pattern (Arrange, Act, Assert)

```php
test('it prevents starting a sprint when another is active', function () {
    // Arrange
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $newSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planning]);

    // Act & Assert
    expect(fn () => app(StartSprintAction::class)->execute($newSprint))
        ->toThrow(ActiveSprintAlreadyExistsException::class);
});
```

### 5.3 Test Helpers

```php
// tests/TestCase.php veya Pest.php
function createProjectWithMember(ProjectRole $role = ProjectRole::Member): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMembership::factory()->create([
        'user_id'    => $user->id,
        'project_id' => $project->id,
        'role'       => $role,
    ]);

    return [$user, $project];
}

function actingAsMember(Project $project, ProjectRole $role = ProjectRole::Member): User
{
    $user = User::factory()->create();
    ProjectMembership::factory()->create([
        'user_id'    => $user->id,
        'project_id' => $project->id,
        'role'       => $role,
    ]);
    test()->actingAs($user);
    return $user;
}
```

### 5.4 Factory Kullanımı

```php
// Tüm testlerde Factory + Faker kullanılır. Hard-coded veri YASAK.

// ✅ Doğru
$story = UserStory::factory()->for($project)->create();
$story = UserStory::factory()->create(['status' => StoryStatus::InProgress]);

// ❌ Yanlış
$story = UserStory::create(['title' => 'Test', 'project_id' => 1]); // hard-coded ID
```

### 5.5 Database Refresh

```php
// Her test RefreshDatabase trait kullanır (Pest'te otomatik)
uses(RefreshDatabase::class);

// SQLite in-memory test DB
// phpunit.xml:
// <env name="DB_CONNECTION" value="sqlite"/>
// <env name="DB_DATABASE" value=":memory:"/>
```

---

## 6. Test Çalıştırma

```bash
# Tüm testler
php artisan test

# Kategori bazında
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Paralel çalıştırma
php artisan test --parallel

# Belirli test dosyası
php artisan test tests/Feature/Scrum/SprintWorkflowTest.php

# Filtre
php artisan test --filter="sprint"

# Coverage raporu
php artisan test --coverage --min=80
```

---

## 7. Coverage Hedefleri

| Katman | Minimum Coverage |
|--------|-----------------|
| Actions | %90 |
| Services | %85 |
| Policies | %100 |
| Controllers | %80 |
| Livewire | %75 |
| **Toplam** | **%80** |

**Not:** Model accessor/mutator ve basit CRUD için %100 hedeflenmez. Kritik business logic coverage öncelikli.

---

## 8. CI Pipeline (GitHub Actions)

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: test
          POSTGRES_PASSWORD: secret
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_pgsql, redis, pcntl, zip
          coverage: xdebug
      
      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist
      
      - name: Copy Environment
        run: cp .env.example .env && php artisan key:generate
      
      - name: Run Tests
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_DATABASE: testing
          DB_USERNAME: test
          DB_PASSWORD: secret
        run: php artisan test --parallel --coverage --min=80
```

---

## 9. Test Öncelikleri (MVP)

```
Öncelik 1 (Sprint 0): Auth + Project CRUD testleri
Öncelik 2 (Sprint 1): User Story + Sprint workflow + RBAC testleri
Öncelik 3 (Sprint 2): Task + Issue + State Machine testleri
Öncelik 4 (Sprint 3): Analytics + Notification + File testleri
Öncelik 5 (Sprint 4): Livewire component testleri
```

Her sprint'te **önce test yazılır, sonra implementasyon başlar** (TDD).

---

**Önceki:** [12-FILE_MANAGEMENT.md](./12-FILE_MANAGEMENT.md)
**Sonraki:** [14-CODING_STANDARDS.md](./14-CODING_STANDARDS.md)
