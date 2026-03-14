# 🔍 Canopy — Tam Optimizasyon Denetim Raporu

**Tarih:** 2026-03-14
**Denetçi:** Kıdemli Optimizasyon Mühendisi
**Kapsam:** Tüm kod tabanı (Controllers, Services, Actions, Models, Listeners, Events, Migrations, Routes, Resources, Traits)

---

## 1) Optimizasyon Özeti

### Mevcut Durum

Canopy kod tabanı **genel olarak iyi yapılandırılmış** bir Laravel projesidir. Controller → Service → Action katmanlı mimarisi doğru uygulanmış, N+1 korumaları (`preventLazyLoading`) aktif ve çoğu endpoint'te eager loading kullanılmaktadır. Ancak **2 kritik söz dizimi hatası** uygulamanın çalışmasını engellemekte ve birkaç orta/yüksek etkili optimizasyon fırsatı mevcuttur.

### En Yüksek Etkili 3 İyileştirme

| # | İyileştirme | Etki | Kategori |
|---|------------|------|----------|
| 1 | **CalculateBurndownAction söz dizimi hatası** — Uygulama çökmesi | Kritik | Güvenilirlik |
| 2 | **SprintController::index eksik parantez** — Derleme hatası | Kritik | Güvenilirlik |
| 3 | **ReorderBacklogAction N+1 döngüsü** — Story sayısı kadar UPDATE sorgusu | Yüksek | Veritabanı / Algoritma |

### Değişiklik Yapılmazsa En Büyük Risk

`CalculateBurndownAction` ve `SprintController` dosyalarındaki söz dizimi hataları, burndown analitik endpoint'inin ve sprint listeleme endpoint'inin **tamamen çalışmamasına** neden olur. Production'da bu endpoint'lere yapılan her istek 500 hatası döndürür.

---

## 2) Bulgular (Öncelik Sırasına Göre)

---

### Bulgu #1 — CalculateBurndownAction Söz Dizimi Hatası

* **Kategori:** Güvenilirlik
* **Şiddet:** Kritik
* **Etki:** Burndown endpoint'i tamamen çalışmaz; 500 Internal Server Error döner
* **Kanıt:** `app/Actions/Analytics/CalculateBurndownAction.php` satır 52-61

```php
// Satır 52-61 — HATALI KOD
$actualLine[] = round($totalPoints - (float) $completedPoints, 1);
->with('userStory')              // ❌ float üzerinde method chain
->get()
->map(fn ($change) => [...])
->values()
->toArray();
```

* **Neden Verimsiz:** `round()` fonksiyonu `float` döndürür. `->with('userStory')` zinciri bir float'a bağlandığı için PHP Fatal Error oluşur. Ayrıca `$scopeChanges` değişkeni hiçbir yerde tanımlanmamış ama `return` içinde kullanılıyor (satır 72).
* **Önerilen Düzeltme:** `$scopeChanges` hesaplaması ayrı bir değişkene atanmalı:

```php
$actualLine[] = round($totalPoints - (float) $completedPoints, 1);
}

$scopeChanges = $sprint->scopeChanges()
    ->with('userStory')
    ->get()
    ->map(fn ($change) => [
        'date' => $change->changed_at->toDateString(),
        'type' => $change->change_type,
        'points_delta' => (float) ($change->userStory->total_points ?? 0),
    ])
    ->values()
    ->toArray();
```

* **Ödünleşim / Riskler:** Yok — tamamen hata düzeltmesi
* **Tahmini Etki:** %100 — endpoint çalışır hale gelir
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #2 — SprintController::index Eksik Parantez

* **Kategori:** Güvenilirlik
* **Şiddet:** Kritik
* **Etki:** Sprint listeleme endpoint'i PHP parse error ile çöker
* **Kanıt:** `app/Http/Controllers/Scrum/SprintController.php` satır 24-26

```php
// Satır 24-26 — HATALI KOD
return SprintResource::collection(
    $project->sprints()->withCount('userStories')->latest()->paginate(request()->integer('per_page', 20))
public function store(CreateSprintRequest $request, Project $project): JsonResponse
```

* **Neden Verimsiz:** `SprintResource::collection(` çağrısı kapatılmamış — `)` ve `; }` eksik. PHP parse error oluşur, tüm controller dosyası yüklenemez.
* **Önerilen Düzeltme:**

```php
return SprintResource::collection(
    $project->sprints()->withCount('userStories')->latest()->paginate(request()->integer('per_page', 20))
);
}
```

* **Ödünleşim / Riskler:** Yok — söz dizimi düzeltmesi
* **Tahmini Etki:** %100 — endpoint çalışır hale gelir
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #3 — ReorderBacklogAction: Döngü İçinde N Adet UPDATE Sorgusu

* **Kategori:** Veritabanı / Algoritma
* **Şiddet:** Yüksek
* **Etki:** 50 story'lik bir backlog'da 50 ayrı UPDATE sorgusu çalışır; gecikme ve DB yükü artar
* **Kanıt:** `app/Actions/Scrum/ReorderBacklogAction.php` satır 19-23

```php
foreach ($orderedIds as $order => $storyId) {
    UserStory::where('id', $storyId)
        ->where('project_id', $project->id)
        ->whereNull('sprint_id')
        ->update(['order' => $order + 1]);
}
```

* **Neden Verimsiz:** Her bir story için ayrı bir SQL UPDATE çalıştırılır. 100 story = 100 sorgu. Bu, özellikle sürükle-bırak UI'ları için yüksek gecikmeye neden olur.
* **Önerilen Düzeltme:** Tek SQL CASE ifadesi veya `upsert()` toplu güncelleme ile:

```php
// Seçenek 1: Parametrize CASE ifadesi (en hızlı, injection-safe)
$cases = [];
$bindings = [];
foreach ($orderedIds as $order => $storyId) {
    $cases[] = 'WHEN id = ? THEN ?';
    $bindings[] = $storyId;
    $bindings[] = $order + 1;
}
if (!empty($bindings)) {
    $caseStatement = implode(' ', $cases);
    $projectId = $project->id;
    DB::update(
        "UPDATE user_stories SET `order` = CASE {$caseStatement} END
         WHERE project_id = ? AND sprint_id IS NULL AND id IN (" . implode(',', array_fill(0, count($orderedIds), '?')) . ")",
        array_merge($bindings, [$projectId], $orderedIds)
    );
}
```

* **Ödünleşim / Riskler:** Raw SQL kullanılması okunabilirliği azaltır; tüm değerler `?` placeholder ile parametrize edilmiş olup SQL injection riski yoktur
* **Tahmini Etki:** 50 sorgu → 1 sorgu (%98 azalma)
* **Kaldırma Güvenliği:** Doğrulama Gerekli
* **Yeniden Kullanım Kapsamı:** Lokal dosya
* **Sınıflandırma:** Yeniden Kullanım Fırsatı — toplu güncelleme pattern'i proje genelinde tekrar kullanılabilir

---

### Bulgu #4 — CloseSprintAction ve ReturnUnfinishedStoriesToBacklog: Tekrarlanan Mantık

* **Kategori:** Veritabanı / Kod Tekrarı
* **Şiddet:** Orta
* **Etki:** Sprint kapatıldığında tamamlanmamış story'lerin backlog'a dönmesi **iki kez** çalışır → 2x gereksiz UPDATE
* **Kanıt:**
  - `app/Actions/Scrum/CloseSprintAction.php` satır 20-23
  - `app/Listeners/ReturnUnfinishedStoriesToBacklog.php` satır 19-21

```php
// CloseSprintAction (execute içinde):
$sprint->userStories()
    ->where('status', '!=', StoryStatus::Done)
    ->update(['sprint_id' => null]);

// ReturnUnfinishedStoriesToBacklog (listener):
UserStory::where('sprint_id', $event->sprint->id)
    ->where('status', '!=', StoryStatus::Done)
    ->update(['sprint_id' => null]);
```

* **Neden Verimsiz:** Aynı mantık hem Action hem Listener'da çalışır. İlk UPDATE tüm story'lerin `sprint_id`'sini null yapar, ikincisi aynı sorguyu tekrar çalıştırır (etkilenen satır = 0 olsa bile DB yükü oluşur). İkili mantık ayrıca bakım riskini artırır — biri değiştirilip diğeri unutulabilir.
* **Önerilen Düzeltme:** Mantığı tek bir yerde tutun. Ya `CloseSprintAction`'dan kaldırın (listener zaten yapıyor) ya da listener'ı kaldırıp sadece Action'da tutun.

```php
// Seçenek A: Action'dan kaldır, Listener'da bırak (event-driven yaklaşım)
class CloseSprintAction {
    public function execute(Sprint $sprint): Sprint {
        $sprint->transitionTo(SprintStatus::Closed->value);
        // Story'lerin backlog'a dönmesi listener'a bırakılır (BR-08)
        return $sprint->fresh();
    }
}
```

* **Ödünleşim / Riskler:** Eğer listener çalışmazsa story'ler sprint'te kalır. Event'in `ShouldQueue` olmamasını doğrulayın.
* **Tahmini Etki:** 1 gereksiz UPDATE sorgusu eliminasyonu + bakım kolaylığı
* **Kaldırma Güvenliği:** Doğrulama Gerekli
* **Yeniden Kullanım Kapsamı:** Modül geneli
* **Sınıflandırma:** Kod Tekrarı — birleştirilmeli

---

### Bulgu #5 — SprintService::delete ile CloseSprintAction Arasında Story Geri Dönüş Tekrarı

* **Kategori:** Kod Tekrarı
* **Şiddet:** Düşük
* **Etki:** Sprint silindiğinde story'lerin backlog'a dönmesi SprintService::delete'te de yapılıyor
* **Kanıt:** `app/Services/SprintService.php` satır 42-43

```php
public function delete(Sprint $sprint): void
{
    $sprint->userStories()->update(['sprint_id' => null]);  // ← Story'leri backlog'a döndür
    $sprint->delete();
}
```

* **Neden Verimsiz:** Story geri dönüş mantığı üç farklı yerde: `CloseSprintAction`, `ReturnUnfinishedStoriesToBacklog` listener, `SprintService::delete`. Bu, Bulgu #4 ile birlikte 3 farklı "story backlog'a dönüş" implementasyonu oluşturur.
* **Önerilen Düzeltme:** Story backlog'a dönüş mantığı tek bir action'da merkezileştirilmeli (örn. `ReturnStoriesToBacklogAction`). Diğer yerler bu action'ı çağırmalı.
* **Ödünleşim / Riskler:** Refactor kapsamı orta
* **Tahmini Etki:** Bakım kolaylığı; kod tekrarı %66 azalır
* **Kaldırma Güvenliği:** Doğrulama Gerekli
* **Yeniden Kullanım Kapsamı:** Modül geneli
* **Sınıflandırma:** Yeniden Kullanım Fırsatı — ortak action çıkarılmalı

---

### Bulgu #6 — NotificationController::index Ekstra Sorgu

* **Kategori:** Veritabanı
* **Şiddet:** Düşük
* **Etki:** Her bildirim listesi isteğinde 1 ekstra COUNT sorgusu çalışır
* **Kanıt:** `app/Http/Controllers/Notification/NotificationController.php` satır 30-31

```php
return NotificationResource::collection($notifications)
    ->additional(['meta' => [
        'unread_count' => $request->user()->notifications()->unread()->count()  // ← Ekstra sorgu
    ]]);
```

* **Neden Verimsiz:** `$notifications` zaten `->unread()` ile filtreleniyor ve paginate ediliyor. `$notifications->total()` paginator'ın zaten döndürdüğü toplam sonuç sayısını verir — ekstra COUNT sorgusuna gerek yok.
* **Önerilen Düzeltme:**

```php
return NotificationResource::collection($notifications)
    ->additional(['meta' => [
        'unread_count' => $notifications->total()  // Paginator'ın zaten döndürdüğü değer
    ]]);
```

* **Ödünleşim / Riskler:** Yok — paginator aynı sorgu sonucunu kullanır
* **Tahmini Etki:** 1 DB sorgusu eliminasyonu (~%50 azalma bu endpoint için)
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #7 — Service Katmanında Tekrarlanan `fresh()` Çağrıları

* **Kategori:** Veritabanı
* **Şiddet:** Düşük
* **Etki:** Her update/assign operasyonunda gereksiz SELECT sorgusu
* **Kanıt:**
  - `SprintService::update()` satır 37 → `$sprint->fresh()`
  - `TaskService::update()` satır 35 → `$task->fresh()`
  - `TaskService::assign()` satır 67 → `$task = $task->fresh()`
  - `UserStoryService::update()` satır 52 → `$story->fresh()`
  - `ProjectService::update()` satır 40 → `$project->fresh()`
  - `MembershipService::changeRole()` satır 60 → `$membership->fresh()`
  - `MoveStoryToSprintAction::execute()` satır 40 → `$story->fresh()`
  - `StartSprintAction::execute()` satır 33 → `$sprint->fresh()`

```php
// Tipik pattern (8 yerde):
$entity->update($data);
return $entity->fresh();  // ← Gereksiz SELECT sorgusu
```

* **Neden Verimsiz:** `update()` çağrısı modeli zaten bellekte günceller. `fresh()` veritabanından yeniden yükler — tamamen gereksiz bir SELECT sorgusu. 8 endpoint'i etkiler.
* **Önerilen Düzeltme:**

```php
$entity->update($data);
return $entity;  // Zaten güncel
// veya ilişkileri yenilemek gerekiyorsa:
return $entity->refresh();  // fresh() yerine refresh() kullanılabilir; yeni instance oluşturmaz
```

* **Ödünleşim / Riskler:** Eğer `update()` sonrası DB trigger'lar veya mutator'lar ek alan değiştiriyorsa `fresh()` gerekli olabilir. Ancak mevcut modellerde böyle bir durum yok.
* **Tahmini Etki:** 8 SELECT sorgusu eliminasyonu (etkilenen endpoint başına ~1 sorgu)
* **Kaldırma Güvenliği:** Büyük Olasılıkla Güvenli
* **Yeniden Kullanım Kapsamı:** Servis geneli (tüm service'ler)
* **Sınıflandırma:** Yeniden Kullanım Fırsatı — pattern değişikliği

---

### Bulgu #8 — AddMemberAction: count() + exists() = 2 Ayrı Sorgu

* **Kategori:** Veritabanı
* **Şiddet:** Düşük
* **Etki:** Üye ekleme işleminde 2 ayrı COUNT/EXISTS sorgusu çalışır
* **Kanıt:** `app/Actions/Project/AddMemberAction.php` satır 27-33

```php
if ($project->memberships()->count() >= self::MAX_MEMBERS) {     // ← Sorgu 1: COUNT
    throw new MaxMembersExceededException(...);
}

$exists = $project->memberships()                                 // ← Sorgu 2: EXISTS
    ->where('user_id', $user->id)
    ->exists();
```

* **Neden Verimsiz:** İki ayrı sorgu yerine tek bir sorgu ile hem üye sayısı hem de mevcut üyelik kontrolü yapılabilir.
* **Önerilen Düzeltme:**

```php
$memberships = $project->memberships()->pluck('user_id');
if ($memberships->count() >= self::MAX_MEMBERS) {
    throw new MaxMembersExceededException(...);
}
if ($memberships->contains($user->id)) {
    throw new DuplicateMemberException(...);
}
```

* **Ödünleşim / Riskler:** `pluck()` küçük veri seti (max 5 üye) için tamamen güvenli
* **Tahmini Etki:** 2 sorgu → 1 sorgu (%50 azalma)
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #9 — ResolvesMembership Trait: Yetersiz Önbellekleme

* **Kategori:** Önbellekleme
* **Şiddet:** Düşük
* **Etki:** Tek request'te farklı projeler için policy kontrolleri yapılırsa cache miss oluşur
* **Kanıt:** `app/Traits/ResolvesMembership.php` satır 15-18

```php
$cached = request()->attributes->get('membership');
if ($cached && $cached->project_id === $project->id && $cached->user_id === $user->id) {
    return $cached->role;
}
```

* **Neden Verimsiz:** Cache anahtarı olarak sadece `'membership'` kullanılıyor. Tek request'te birden fazla proje veya kullanıcı için policy çağrılırsa, sadece son sonuç cache'lenir. Önceki sonuçlar kaybedilir.
* **Önerilen Düzeltme:**

```php
$cacheKey = "membership.{$project->id}.{$user->id}";
$cached = request()->attributes->get($cacheKey);
if ($cached !== null) {
    return $cached;
}

$membership = $user->projectMemberships()
    ->where('project_id', $project->id)
    ->first();

$role = $membership?->role;
request()->attributes->set($cacheKey, $role);

return $role;
```

* **Ödünleşim / Riskler:** Hafif bellek artışı (request bazlı, ihmal edilebilir)
* **Tahmini Etki:** Çoklu proje policy kontrollerinde ~%50 sorgu azaltma
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Modül geneli (7 policy)

---

### Bulgu #10 — BroadcastProjectUpdate ve LogActivity: Boş Listener'lar

* **Kategori:** Ölü Kod
* **Şiddet:** Düşük
* **Etki:** Hiçbir iş yapmayan listener'lar event döngüsünde gereksiz yere çağrılır
* **Kanıt:**
  - `app/Listeners/BroadcastProjectUpdate.php` — boş `handle()` metodu
  - `app/Listeners/LogActivity.php` — boş `handle()` metodu

```php
public function handle(object $event): void
{
    // Broadcasting implementasyonu
    // Laravel Echo + Reverb ile proje kanalına broadcast edilecek.
}
```

* **Neden Verimsiz:** Bu listener'lar `AppServiceProvider`'da kayıtlı değil (şu anda). Ancak dosyalar mevcut ve gelecekte kayıt edildiğinde gereksiz object instantiation ve method çağrısı oluşur. Ölü kod olarak bakım yükü oluşturur.
* **Önerilen Düzeltme:** Implement edilene kadar dosyaları silin veya `@todo` ile açıkça işaretleyin. Event listener kaydı yapılmadığından performans etkisi şu anda yok, ancak ölü kod olarak bakım riskini artırır.
* **Ödünleşim / Riskler:** Gelecekte implement edilecekse silmek yerine `@todo` bırakılabilir
* **Tahmini Etki:** Düşük — şu anda kayıtlı değiller
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya
* **Sınıflandırma:** Ölü Kod — güvenli kaldırma adayı

---

### Bulgu #11 — CalculateBurndownAction: Döngü İçinde Tekrarlanan Koleksiyon Filtreleme

* **Kategori:** Algoritma / CPU
* **Şiddet:** Orta
* **Etki:** Sprint süresi boyunca her gün için tüm done story'ler taranır; O(n×m) karmaşıklık
* **Kanıt:** `app/Actions/Analytics/CalculateBurndownAction.php` satır 42-52

```php
foreach ($period as $date) {
    // ...
    $completedPoints = $doneStories
        ->filter(fn ($s) => $s->updated_at->startOfDay()->lte($date))  // ← Her gün için tüm koleksiyon taranır
        ->sum('total_points');
    $actualLine[] = round($totalPoints - (float) $completedPoints, 1);
}
```

* **Neden Verimsiz:** 2 haftalık sprint (14 gün) + 20 done story → 14 × 20 = 280 filtreleme işlemi. `startOfDay()` her çağrıda yeni Carbon nesnesi oluşturur.
* **Önerilen Düzeltme:** Kümülatif toplama yaklaşımı — story'leri tarihe göre sıralayıp kümülatif puan hesaplayın:

```php
// Story'leri tarihe göre grupla ve kümülatif topla
$dailyCompleted = $doneStories->groupBy(
    fn ($s) => $s->updated_at->toDateString()
)->map(fn ($group) => $group->sum('total_points'));

$cumulativeCompleted = 0;
foreach ($period as $date) {
    if ($date->isFuture()) {
        $actualLine[] = null;
        continue;
    }
    $dateStr = $date->toDateString();
    $cumulativeCompleted += ($dailyCompleted[$dateStr] ?? 0);
    $actualLine[] = round($totalPoints - $cumulativeCompleted, 1);
}
```

* **Ödünleşim / Riskler:** Yok — aynı sonucu üretir, daha hızlı
* **Tahmini Etki:** O(n×m) → O(n+m); ~%80 CPU tasarrufu (büyük sprint'lerde)
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #12 — EpicController::index Sayfalama Eksikliği

* **Kategori:** Veritabanı / Bellek
* **Şiddet:** Düşük-Orta (veri büyümesiyle artar)
* **Etki:** Tüm epic'ler bellekte yüklenir — büyük projelerde bellek sorunu
* **Kanıt:** `app/Http/Controllers/Scrum/EpicController.php` satır 24

```php
$epics = $project->epics()->withCount('userStories')->get();  // ← get() = tümünü yükle
```

* **Neden Verimsiz:** `get()` tüm sonuçları belleğe yükler. Projedeki epic sayısı arttıkça bellek kullanımı ve yanıt süresi artar. Diğer endpoint'ler (`stories`, `sprints`, `issues`) `paginate()` kullanırken epic kullanmıyor.
* **Önerilen Düzeltme:**

```php
$epics = $project->epics()
    ->withCount('userStories')
    ->paginate(request()->integer('per_page', 20));
```

* **Ödünleşim / Riskler:** Frontend'in sayfalama desteği gerekir
* **Tahmini Etki:** Büyük projelerde bellek kullanımı ~%70 azalır
* **Kaldırma Güvenliği:** Büyük Olasılıkla Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #13 — TaskController::index Sayfalama Eksikliği

* **Kategori:** Veritabanı / Bellek
* **Şiddet:** Düşük-Orta
* **Etki:** Bir story'nin tüm task'ları sayfalama olmadan yüklenir
* **Kanıt:** `app/Http/Controllers/Scrum/TaskController.php` satır 28

```php
return TaskResource::collection($story->tasks()->with('assignee')->get());  // ← Tümünü yükle
```

* **Neden Verimsiz:** Genellikle bir story'nin az task'ı olur, ancak sınırsız `get()` kullanımı kötü pratiktir.
* **Önerilen Düzeltme:** `paginate()` veya `limit()` eklenmesi düşünülebilir. Task sayısı genellikle düşük olduğundan düşük önceliklidir.
* **Ödünleşim / Riskler:** Düşük — task sayısı genelde sınırlı
* **Tahmini Etki:** Düşük
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #14 — SendStatusChangeNotification: Olası Lazy Loading

* **Kategori:** Veritabanı / N+1
* **Şiddet:** Düşük
* **Etki:** Story/issue creator ve task assignee ilişkileri lazy load edilebilir
* **Kanıt:** `app/Listeners/SendStatusChangeNotification.php` satır 40, 66, 86

```php
// handleStoryStatusChanged:
$creator = $story->creator;          // ← Lazy load tetiklenebilir

// handleTaskStatusChanged:
$task->assignee                      // ← Lazy load tetiklenebilir

// handleIssueStatusChanged:
$creator = $issue->creator;          // ← Lazy load tetiklenebilir
```

* **Neden Verimsiz:** Listener'lara gelen event'lerdeki model'ler ilişkileri yüklü olmayabilir. `preventLazyLoading` development'ta bunu yakalayacaktır, ancak production'da sessizce N+1 oluşur.
* **Önerilen Düzeltme:** Listener başında `loadMissing()` kullanın:

```php
$story->loadMissing('creator');
$task->loadMissing('assignee');
$issue->loadMissing('creator');
```

* **Ödünleşim / Riskler:** Çok düşük — zaten ihtiyaç duyulan veri
* **Tahmini Etki:** Potansiyel N+1 engelleme
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #15 — Project::scopeActive Gereksiz Scope

* **Kategori:** Ölü Kod
* **Şiddet:** Düşük
* **Etki:** Gereksiz scope, SoftDeletes zaten aynı işi yapar
* **Kanıt:** `app/Models/Project.php` satır 88-91

```php
public function scopeActive($query)
{
    return $query->whereNull('deleted_at');  // SoftDeletes zaten bunu yapıyor
}
```

* **Neden Verimsiz:** Laravel'in `SoftDeletes` trait'i otomatik olarak `deleted_at IS NULL` koşulunu tüm sorgulara ekler. Bu scope tamamen gereksizdir ve yanıltıcı olabilir.
* **Önerilen Düzeltme:** Scope'u kaldırın veya `withTrashed()` ile birlikte kullanmak için yeniden tanımlayın.
* **Ödünleşim / Riskler:** Eğer başka yerlerde kullanılıyorsa kırılma olabilir — kullanım araştırılmalı
* **Tahmini Etki:** Düşük — kod temizliği
* **Kaldırma Güvenliği:** Doğrulama Gerekli
* **Yeniden Kullanım Kapsamı:** Lokal dosya
* **Sınıflandırma:** Ölü Kod — doğrulama sonrası güvenli kaldırma adayı

---

### Bulgu #16 — Task::getProjectAttribute Accessor'ında Lazy Loading Zinciri

* **Kategori:** Veritabanı / N+1
* **Şiddet:** Düşük
* **Etki:** `$task->project` erişiminde 2 lazy load sorgusu tetiklenir
* **Kanıt:** `app/Models/Task.php` satır 83-86

```php
public function getProjectAttribute(): ?Project
{
    return $this->userStory?->project;  // ← userStory lazy load + project lazy load = 2 sorgu
}
```

* **Neden Verimsiz:** Bu accessor her çağrıldığında potansiyel olarak 2 sorgu tetikler: birincisi `userStory`, ikincisi `project`. Policy katmanında kullanılırsa ciddi N+1 riski taşır.
* **Önerilen Düzeltme:** Bu accessor'ı kullanan kodlarda `$task->loadMissing('userStory.project')` çağrılmalı. Nitekim `TaskController::changeStatus` ve `TaskController::destroy` zaten bunu yapıyor — iyi pratik.
* **Ödünleşim / Riskler:** Yok
* **Tahmini Etki:** Düşük — dikkat gerektiren alan
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #17 — BurndownService ve VelocityService: Aşırı İnce Sarmalayıcılar

* **Kategori:** Aşırı Soyutlama
* **Şiddet:** Düşük
* **Etki:** Gereksiz dolaylama (indirection) — bakım karmaşıklığı
* **Kanıt:**
  - `app/Services/BurndownService.php` — sadece `calculateAction->execute()` çağırır
  - `app/Services/VelocityService.php` — sadece `calculateAction->execute()` çağırır

```php
// BurndownService — tüm dosya:
class BurndownService {
    public function __construct(private readonly CalculateBurndownAction $calculateAction) {}
    public function getBurndownData(Sprint $sprint): array {
        return $this->calculateAction->execute($sprint);
    }
}
```

* **Neden Verimsiz:** Bu servisler hiçbir ek mantık (transaction, event dispatch, hata yönetimi) içermiyor. Sadece action'a iletiyorlar. Controller doğrudan action'ı da çağırabilir (katman kurallarına göre yapılamaz ama servisin bir değer katması gerekir).
* **Önerilen Düzeltme:** Bu servisler şu anda mimari tutarlılık için var. Gelecekte cache stratejisi, loglama veya rate limiting eklenecekse mantıklı. Aksi halde Controller → Action kısayolu değerlendirilebilir.
* **Ödünleşim / Riskler:** Mimari tutarlılığı bozar; ancak pragmatik yaklaşım olarak kabul edilebilir
* **Tahmini Etki:** Düşük — kod temizliği
* **Kaldırma Güvenliği:** Büyük Olasılıkla Güvenli
* **Yeniden Kullanım Kapsamı:** Modül geneli
* **Sınıflandırma:** Aşırı Soyutlama — netlik/performans kazancı olmadan dolaylama ekler

---

### Bulgu #18 — Veritabanı İndeks Eksiklikleri

* **Kategori:** Veritabanı
* **Şiddet:** Düşük-Orta
* **Etki:** Bazı sık kullanılan sorgu pattern'lerinde tam tablo taraması riski
* **Kanıt:** Mevcut migration'lar ve sorgu pattern'leri incelenerek:

| Tablo | Sütun | Kullanıldığı Yer | İndeks Durumu |
|-------|-------|------------------|---------------|
| `notifications` | `user_id, read_at` | `NotificationController::index` — `unread()` scope | ❌ Bileşik indeks eksik |
| `user_stories` | `sprint_id` | `SprintController`, `CloseSprintAction` | ❌ İndeks eksik (FK var ama açık indeks yok) |
| `user_stories` | `status` | `CalculateBurndownAction`, `ChangeStoryStatusAction` | ❌ İndeks eksik |
| `issues` | `assigned_to` | `IssueController::index` (`assigned_to = me`) | ❌ İndeks eksik |
| `issues` | `status` | `IssueController::index` filtresi | ❌ İndeks eksik |
| `project_memberships` | `user_id, project_id` | `ResolvesMembership`, `Project::forUser` | ⚠️ Bileşik indeks önerilir |

* **Önerilen Düzeltme:** Yeni migration ile eksik indeksleri ekleyin:

```php
Schema::table('notifications', function (Blueprint $table) {
    $table->index(['user_id', 'read_at']);
});

Schema::table('user_stories', function (Blueprint $table) {
    $table->index('sprint_id');
    $table->index('status');
});

Schema::table('issues', function (Blueprint $table) {
    $table->index('assigned_to');
    $table->index('status');
});

Schema::table('project_memberships', function (Blueprint $table) {
    $table->unique(['user_id', 'project_id']);  // Hem indeks hem uniqueness
});
```

* **Ödünleşim / Riskler:** İndeksler yazma işlemlerini marjinal olarak yavaşlatır, ancak okuma performansını önemli ölçüde artırır
* **Tahmini Etki:** Filtreleme sorgularında %50-90 hızlanma (veri büyüklüğüne bağlı)
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Servis geneli

---

### Bulgu #19 — MembershipService::remove: Event Dispatch Transaction İçinde

* **Kategori:** Güvenilirlik
* **Şiddet:** Düşük
* **Etki:** Transaction rollback olursa event zaten dispatch edilmiş olur; tutarsız durum riski
* **Kanıt:** `app/Services/MembershipService.php` satır 44-48

```php
public function remove(Project $project, User $user, User $removedBy): void
{
    DB::transaction(function () use ($project, $user, $removedBy) {
        $this->removeAction->execute($project, $user);
        MemberRemoved::dispatch($project, $user, $removedBy);  // ← Transaction içinde
    });
}
```

* **Neden Verimsiz:** Diğer servisler (`SprintService`, `UserStoryService`) event dispatch'i transaction **dışında** yapıyor ve `BroadcastException` yakalıyor. Bu tutarsız pattern. Eğer `MemberRemoved` event'i senkron listener'lar tetiklerse ve bunlar başarısız olursa, transaction rollback olur ama listener yan etkileri geri alınamaz.
* **Önerilen Düzeltme:** Event dispatch'i transaction dışına taşıyın, diğer servislerle tutarlı hale getirin:

```php
public function remove(Project $project, User $user, User $removedBy): void
{
    DB::transaction(function () use ($project, $user) {
        $this->removeAction->execute($project, $user);
    });

    try {
        MemberRemoved::dispatch($project, $user, $removedBy);
    } catch (BroadcastException $e) {
        Log::warning('Broadcast failed for MemberRemoved', ['error' => $e->getMessage()]);
    }
}
```

* **Ödünleşim / Riskler:** Düşük — tutarlılık iyileşir
* **Tahmini Etki:** Güvenilirlik artışı; tutarsız state riski azalır
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

### Bulgu #20 — SnapshotDailyBurndownAction: String Karşılaştırması

* **Kategori:** Güvenilirlik / Kod Kalitesi
* **Şiddet:** Düşük
* **Etki:** Enum karşılaştırmasında tip güvenliği eksik
* **Kanıt:** `app/Actions/Analytics/SnapshotDailyBurndownAction.php` satır 17

```php
if ($sprint->status->value !== 'active') {
    return;
}
```

* **Neden Verimsiz:** String literal `'active'` ile karşılaştırma, enum yeniden adlandırılırsa sessizce kırılır. Diğer dosyalar `SprintStatus::Active` enum sabitini kullanırken bu dosya string kullanıyor — tutarsızlık.
* **Önerilen Düzeltme:**

```php
if ($sprint->status !== SprintStatus::Active) {
    return;
}
```

* **Ödünleşim / Riskler:** Yok
* **Tahmini Etki:** Düşük — tip güvenliği iyileşir
* **Kaldırma Güvenliği:** Güvenli
* **Yeniden Kullanım Kapsamı:** Lokal dosya

---

## 3) Hızlı Kazanımlar (Önce Yapılacaklar)

| # | İyileştirme | Uygulama Süresi | Etki | Bulgu Ref |
|---|------------|-----------------|------|-----------|
| 1 | CalculateBurndownAction söz dizimi düzeltmesi | 5 dk | Kritik — endpoint çalışır | #1 |
| 2 | SprintController::index eksik parantez düzeltmesi | 2 dk | Kritik — endpoint çalışır | #2 |
| 3 | NotificationController ekstra sorgu kaldırma | 2 dk | Düşük — 1 sorgu eliminasyonu | #6 |
| 4 | SnapshotDailyBurndownAction enum karşılaştırması | 1 dk | Düşük — tip güvenliği | #20 |
| 5 | Service'lerde gereksiz `fresh()` çağrılarını kaldırma | 10 dk | Düşük — 8 sorgu eliminasyonu | #7 |
| 6 | MembershipService event dispatch tutarlılığı | 5 dk | Düşük — güvenilirlik | #19 |

---

## 4) Derin Optimizasyonlar (Sonra Yapılacaklar)

| # | İyileştirme | Uygulama Süresi | Etki | Bulgu Ref |
|---|------------|-----------------|------|-----------|
| 1 | ReorderBacklogAction toplu UPDATE | 1-2 saat | Yüksek — N→1 sorgu | #3 |
| 2 | CloseSprintAction / Listener tekrar mantığının merkezileştirilmesi | 1 saat | Orta — bakım kolaylığı | #4, #5 |
| 3 | Burndown kümülatif hesaplama optimizasyonu | 30 dk | Orta — O(n×m) → O(n+m) | #11 |
| 4 | Veritabanı indeks ekleme migration'ı | 30 dk | Orta — sorgu performansı | #18 |
| 5 | ResolvesMembership çoklu proje cache desteği | 20 dk | Düşük-Orta — N+1 engelleme | #9 |
| 6 | EpicController sayfalama eklenmesi | 15 dk | Düşük-Orta — bellek koruması | #12 |
| 7 | Listener'larda `loadMissing()` eklenmesi | 15 dk | Düşük — N+1 engelleme | #14 |
| 8 | Ölü listener'ların temizlenmesi | 5 dk | Düşük — kod temizliği | #10 |
| 9 | Project::scopeActive kaldırılması | 5 dk | Düşük — kod temizliği | #15 |

---

## 5) Doğrulama Planı

### 5.1 Benchmark'lar

```bash
# Burndown endpoint yanıt süresi (düzeltme öncesi/sonrası):
curl -w "%{time_total}" -s -o /dev/null -X GET \
  http://localhost/api/projects/{slug}/sprints/{id}/burndown

# Sprint listesi endpoint yanıt süresi:
curl -w "%{time_total}" -s -o /dev/null -X GET \
  http://localhost/api/projects/{slug}/sprints

# Reorder endpoint (50 story):
time curl -X PUT http://localhost/api/projects/{slug}/stories/reorder \
  -d '{"ordered_ids": ["id1", ..., "id50"]}'
```

### 5.2 Profiling Stratejisi

```php
// Laravel Debugbar veya Telescope ile:
// 1. Her endpoint için sorgu sayısı izleme
// 2. Bellek kullanımı izleme
// 3. Event listener çalışma süreleri

// Telescope kurulumu:
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

// Ayrıca:
DB::enableQueryLog();
// ... endpoint çağrısı ...
dd(DB::getQueryLog());  // Sorgu sayısı ve süreleri
```

### 5.3 Karşılaştırma Metrikleri

| Metrik | Öncesi (Beklenen) | Sonrası (Hedef) | Ölçüm Yöntemi |
|--------|-------------------|-----------------|----------------|
| Burndown endpoint | 500 Error | 200 OK + <200ms | HTTP status + timing |
| Sprint listesi | 500 Error | 200 OK + <100ms | HTTP status + timing |
| Reorder (50 item) | 50 SQL UPDATE | 1 SQL UPDATE | Query log |
| Sprint close | 2 UPDATE (tekrarlı) | 1 UPDATE | Query log |
| Notification list | 2 sorgu | 1 sorgu | Query log |
| Service update | 2 sorgu (UPDATE + SELECT) | 1 sorgu (UPDATE) | Query log |

### 5.4 Doğruluk Testleri

```bash
# Mevcut test suite'ini çalıştırarak regresyon olmadığını doğrulayın:
php artisan test

# Özellikle bu test dosyalarını kontrol edin:
php artisan test --filter=BurndownTest
php artisan test --filter=SprintTest
php artisan test --filter=UserStoryTest
php artisan test --filter=ReorderTest

# Yeni testler eklenmeli:
# - CalculateBurndownAction scope_changes doğru döner mü?
# - ReorderBacklogAction toplu güncelleme doğru sıralama yapar mı?
# - CloseSprintAction tekrarlı story backlog dönüşü olmuyor mu?
```

---

## 6) Optimize Edilmiş Kod / Yama Önerileri

### 6.1 CalculateBurndownAction — Tam Düzeltme

```php
<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\StoryStatus;
use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CalculateBurndownAction
{
    public function execute(Sprint $sprint): array
    {
        $startDate = Carbon::parse($sprint->start_date);
        $endDate = Carbon::parse($sprint->end_date);
        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = $startDate->diffInDays($endDate);

        $totalPoints = (float) $sprint->userStories()->sum('total_points');

        // İdeal çizgi
        $idealLine = [];
        $dailyBurn = $totalDays > 0 ? $totalPoints / $totalDays : 0;
        foreach ($period as $index => $date) {
            $idealLine[] = round($totalPoints - ($dailyBurn * $index), 1);
        }

        // Gerçek çizgi — kümülatif hesaplama (O(n+m))
        $doneStories = $sprint->userStories()
            ->where('status', StoryStatus::Done)
            ->select('id', 'total_points', 'updated_at')
            ->get();

        $dailyCompleted = $doneStories->groupBy(
            fn ($s) => $s->updated_at->toDateString()
        )->map(fn ($group) => $group->sum('total_points'));

        $actualLine = [];
        $cumulativeCompleted = 0;
        foreach ($period as $date) {
            if ($date->isFuture()) {
                $actualLine[] = null;
                continue;
            }
            $cumulativeCompleted += (float) ($dailyCompleted[$date->toDateString()] ?? 0);
            $actualLine[] = round($totalPoints - $cumulativeCompleted, 1);
        }

        // Scope changes
        $scopeChanges = $sprint->scopeChanges()
            ->with('userStory')
            ->get()
            ->map(fn ($change) => [
                'date' => $change->changed_at->toDateString(),
                'type' => $change->change_type,
                'points_delta' => (float) ($change->userStory->total_points ?? 0),
            ])
            ->values()
            ->toArray();

        return [
            'sprint' => [
                'name' => $sprint->name,
                'start_date' => $sprint->start_date,
                'end_date' => $sprint->end_date,
            ],
            'total_points' => $totalPoints,
            'ideal_line' => $idealLine,
            'actual_line' => $actualLine,
            'scope_changes' => $scopeChanges,
        ];
    }
}
```

**Değişiklikler:**
1. ✅ Söz dizimi hatası düzeltildi (`$scopeChanges` ayrı değişkene atandı)
2. ✅ `foreach` döngüsü kapatıldı (eksik `}`)
3. ✅ Kümülatif hesaplama ile O(n×m) → O(n+m) optimizasyonu
4. ✅ Kullanılmayan `SprintStatus` import'u kaldırıldı

---

### 6.2 SprintController::index — Söz Dizimi Düzeltmesi

```php
public function index(Project $project): AnonymousResourceCollection
{
    return SprintResource::collection(
        $project->sprints()->withCount('userStories')->latest()->paginate(request()->integer('per_page', 20))
    );
}
```

**Değişiklik:** Eksik `);` ve `}` eklendi.

---

### 6.3 ReorderBacklogAction — Toplu UPDATE

```php
<?php

declare(strict_types=1);

namespace App\Actions\Scrum;

use App\Models\Project;
use App\Models\UserStory;
use Illuminate\Support\Facades\DB;

class ReorderBacklogAction
{
    public function execute(Project $project, array $orderedIds): void
    {
        if (empty($orderedIds)) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($orderedIds as $order => $storyId) {
            $cases[] = 'WHEN id = ? THEN ?';
            $bindings[] = $storyId;
            $bindings[] = $order + 1;
        }

        $caseStatement = implode(' ', $cases);
        $projectId = $project->id;

        DB::update(
            "UPDATE user_stories SET `order` = CASE {$caseStatement} END
             WHERE project_id = ? AND sprint_id IS NULL AND id IN (" . implode(',', array_fill(0, count($orderedIds), '?')) . ')',
            array_merge($bindings, [$projectId], $orderedIds)
        );
    }
}
```

**Değişiklik:** N adet UPDATE → 1 adet UPDATE (CASE ifadesi ile).

---

### 6.4 NotificationController::index — Ekstra Sorgu Eliminasyonu

```php
public function index(Request $request): AnonymousResourceCollection
{
    $notifications = $request->user()
        ->notifications()
        ->unread()
        ->latest()
        ->paginate(20);

    return NotificationResource::collection($notifications)
        ->additional(['meta' => ['unread_count' => $notifications->total()]]);
}
```

**Değişiklik:** `$request->user()->notifications()->unread()->count()` → `$notifications->total()`

---

## Ek: Kontrol Listesi Özeti

| Kontrol Alanı | Durum | Bulgular |
|---------------|-------|----------|
| Algoritma & Veri Yapıları | ⚠️ | #3 (N sorgu döngüsü), #11 (O(n×m) filtreleme) |
| Bellek | ✅ | #12 ve #13 düşük seviyeli sayfalama eksiklikleri |
| I/O & Ağ | ✅ | Sorun yok |
| Veritabanı / Sorgu Performansı | ⚠️ | #3, #6, #7, #8, #18 (indeks eksiklikleri) |
| Eşzamanlılık / Asenkron | ✅ | Sorun yok |
| Önbellekleme | ⚠️ | #9 (ResolvesMembership yetersiz cache) |
| Frontend / UI | ℹ️ | Livewire bileşen dosyaları bulunamadı — ayrı değerlendirme gerekli |
| Güvenilirlik / Maliyet | ⚠️ | #1, #2 (kritik hatalar), #19 (event tutarlılığı) |
| Kod Tekrarı & Ölü Kod | ⚠️ | #4, #5 (tekrarlanan mantık), #10, #15 (ölü kod), #17 (aşırı soyutlama) |

---

*Bu rapor, kod tabanının mevcut durumunu yansıtmaktadır. Optimizasyonlar öncelik sırasına göre uygulanmalıdır. Kritik bulgular (#1, #2) acil düzeltme gerektirir.*
