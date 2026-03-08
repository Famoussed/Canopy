# 04 — Business Rules

Sistemin temel iş kuralları, Scrum workflow mantığı, hesaplama formülleri ve kısıtlamalar.

**İlişkili Dokümanlar:** [State Machine](./03-STATE_MACHINE.md) | [Analytics Engine](./10-ANALYTICS_ENGINE.md) | [RBAC](./05-RBAC_PERMISSIONS.md)

---

## 1. Backlog Yönetimi

### BR-01: Varsayılan Story Durumu

> Eklenen her yeni User Story varsayılan olarak `new` durumunda ve `sprint_id = NULL` (Backlog'da) oluşturulur.

**Uygulama yeri:** `CreateUserStoryAction`

```
Girdi:  {title, description, project_id, epic_id?}
Çıktı:  UserStory(status='new', sprint_id=NULL, order=max+1)
```

### BR-02: Backlog Sıralaması

> Backlog'daki hikayeler `order` alanına göre sıralanır. Yeni eklenen hikaye listenin sonuna eklenir. Drag-drop ile sıralama değiştirilebilir.

**Uygulama yeri:** `ReorderBacklogAction`

---

## 2. Puanlama (Estimation) {#puanlama}

### BR-03: Rol Bazlı Puanlama

> Story'ler, proje ayarlarında tanımlanan ekip rolleri bazında (örn. UX, Design, Frontend, Backend) ayrı ayrı puanlanabilir.

**Kaynak:** `Project.settings.estimation_roles` → `["UX", "Design", "Frontend", "Backend"]`

**Veri yapısı:**
```
story_points tablosu:
  | user_story_id | role_name | points |
  |---------------|-----------|--------|
  | story-1       | UX        | 3      |
  | story-1       | Design    | 5      |
  | story-1       | Frontend  | 8      |
  | story-1       | Backend   | 5      |
```

### BR-04: Toplam Puan Hesaplama

> Bir User Story'nin `total_points` değeri, `story_points` kayıtlarının toplamıdır.

**Formül:**
```
total_points = SUM(story_points.points WHERE user_story_id = this.id)
```

**Uygulama yeri:** `CalculateStoryPointsAction` — StoryPoint eklendiğinde/güncellendiğinde `UserStory.total_points` güncellenir.

**Kural:** `total_points` doğrudan set edilmez, her zaman hesaplanır.

---

## 3. Sprint Yönetimi

### BR-05: Tek Aktif Sprint

> Bir projede aynı anda yalnızca **1 aktif Sprint** (`status = active`) olabilir.

**Uygulama yeri:** `SprintService.start()` — Başlatmadan önce aktif sprint var mı kontrol et.

**Hata:** `ActiveSprintAlreadyExistsException` (422)

### BR-06: Sprint Planlama

> Bir Sprint oluşturulduğunda `status = planning` olur. Backlog'daki hikayeler kapasite yettiğince Sprint'e çekilir (sürükle-bırak veya toplu atama).

**Akış:**
```
1. Sprint oluştur (planning)
2. Backlog'dan story'leri Sprint'e taşı
3. Sprint'i başlat (planning → active)
4. Sprint süresince çalış
5. Sprint'i kapat (active → closed)
```

### BR-07: Sprint Tarih Kuralları

> - `end_date` > `start_date` olmalı
> - Aktif sprint'in `start_date`'i geçmiş olamaz (başlatıldığında bugünün tarihi set edilir)
> - Sprint süresi minimum 1 gün, maksimum 30 gün

### BR-08: Sprint Kapatma

> Sprint kapatıldığında:
> 1. Sprint durumu `closed` olur
> 2. Sprint'teki `done` olmayan story'ler otomatik olarak Backlog'a geri döner (`sprint_id = NULL`)
> 3. Velocity hesaplaması güncellenir

**Uygulama yeri:** `SprintService.close()`

---

## 4. Sprint Scope Change (Kapsam Değişikliği)

### BR-09: Scope Change Algılama

> Devam eden (active) bir Sprint'e yeni bir hikaye eklenirse veya çıkarılırsa, bu "Sprint Scope Change" olarak işaretlenir.

**Koşul:** Sprint.status === `active`

**Akış:**
```
Story Sprint'e taşınıyor
    │
    ├── Sprint.status === 'planning' ?
    │       → Normal taşıma, scope change kaydı YOK
    │
    └── Sprint.status === 'active' ?
            → SprintScopeChange kaydı oluştur
            → SprintScopeChanged event'i fırlat
            → Burndown grafiğine yansıt
```

**Uygulama yeri:** `MoveStoryToSprintAction` + `DetectScopeChangeAction`

**Veri kaydı:**
```
sprint_scope_changes:
  | sprint_id | user_story_id | change_type | changed_at          | changed_by |
  |-----------|---------------|-------------|---------------------|------------|
  | sprint-1  | story-5       | added       | 2026-03-10 14:30:00 | user-1     |
  | sprint-1  | story-3       | removed     | 2026-03-12 09:15:00 | user-2     |
```

### BR-10: Scope Change Burndown Etkisi

> - **Ekleme:** Sprint'in toplam puanı artar, burndown grafiğinde yukarı "basamak" oluşur
> - **Çıkarma:** Sprint'in toplam puanı azalır, burndown grafiğinde aşağı "basamak" oluşur

Detay: [10-ANALYTICS_ENGINE.md](./10-ANALYTICS_ENGINE.md)

---

## 5. Epic Tamamlanma {#epic-tamamlanma}

### BR-11: Epic Tamamlanma Yüzdesi

> Bir Epic'in tamamlanma yüzdesi, içindeki User Story'lerin durumuna göre otomatik hesaplanır.

**Formül:**
```
completion_percentage = (done_stories_count / total_stories_count) × 100
```

**Özel durumlar:**
- Epic'te hiç story yoksa → %0
- Tüm story'ler done ise → %100
- Küsuratlı değerler floor ile yuvarlanır (67.8% → 67%)

**Tetikleme:** `StoryStatusChanged` event'i → `RecalculateEpicCompletion` listener → `CalculateEpicCompletionAction`

**Uygulama yeri:** `CalculateEpicCompletionAction`

```
1. Story'nin epic_id'si var mı? Yoksa → çık
2. Epic'teki toplam story sayısını al
3. Epic'teki done story sayısını al
4. Yüzdeyi hesapla
5. Epic.status otomatik güncelle:
   - %0 → 'new'
   - %1-99 → 'in_progress'
   - %100 → 'done'
```

---

## 6. Üyelik Kuralları

### BR-12: Tekil Üyelik

> Bir kullanıcı aynı projeye birden fazla kez eklenemez.

**Constraint:** `UNIQUE(project_id, user_id)` on `project_memberships`

**Hata:** `DuplicateMemberException` (422)

### BR-12.1: Maksimum Üye Limiti

> Bir proje en fazla 5 üyeye sahip olabilir.

**Uygulama yeri:** `AddMemberAction.execute()` içinde `memberships()->count()` kontrolü

**Hata:** `MaxMembersExceededException` (422)

### BR-13: Proje Sahibi Üyelik

> Proje oluşturulduğunda, oluşturan kullanıcı otomatik olarak `owner` rolüyle üye yapılır.

**Uygulama yeri:** `ProjectService.create()` içinde `CreateProjectAction` + `AddMemberAction(role: owner)`

### BR-14: Owner Çıkarılamaz

> Proje sahibi (owner) projeden çıkarılamaz. Owner değişikliği sadece "devretme" (transfer) ile yapılabilir.

**Uygulama yeri:** `MembershipPolicy.delete()` + `MembershipService.remove()`

### BR-15: Proje Silme Cascade

> Proje silindiğinde (soft delete):
> - Proje `deleted_at` set edilir
> - İlişkili tüm veriler korunur (soft delete cascade)
> - Sadece Owner silme yetkisine sahiptir

---

## 7. Task Kuralları

### BR-16: Task Atanmadan Başlatılamaz

> Bir Task `new` → `in_progress` geçişi yapabilmek için `assigned_to` alanı dolu olmalıdır.

Detay: [03-STATE_MACHINE.md](./03-STATE_MACHINE.md)

### BR-17: Task Düzenle ve Durum Değiştirme Yetkisi

> Task düzenle ve durum değiştirme yetkisi yalnızca aşağıdaki kullanıcılara aittir:
> - Task'ı oluşturan kullanıcı (`created_by`)
> - Modüratör rolündeki kullanıcılar
> - Owner
>
> Tüm proje üyeleri (Member dahil) task oluşturabilir.
> Member rolündeki kullanıcı:
> - Sadece kendi oluşturduğu task'ların durumunu değiştirebilir ve düzenleyebilir
> - Başka kullanıcılara task atayamaz

Detay: [05-RBAC_PERMISSIONS.md](./05-RBAC_PERMISSIONS.md)

---

## 8. Issue Kuralları

### BR-18: Issue Varsayılan Değerler

> Yeni oluşturulan Issue'nun varsayılan değerleri:
> - `status`: `new`
> - `priority`: `normal`
> - `severity`: `minor`
> - `type`: Kullanıcı tarafından seçilmeli (zorunlu alan)

### BR-19: Member Issue Yetkisi

> Member rolündeki kullanıcı:
> - Issue oluşturabilir (herkes)
> - Sadece kendi oluşturduğu issue'ları düzenleyebilir
> - Sadece kendi oluşturduğu issue'ları silebilir
> - Başkalarının issue'larını düzenleyemez veya silemez
> - Issue atayamaz (sadece Owner/Moderator atayabilir)
>
> Owner/Moderator:
> - Tüm issue'ları düzenleyebilir ve silebilir
> - Issue atayabilir

---

## 9. İş Kuralları Özet Tablosu

| Kural | Kod | Konum | Hata |
|-------|-----|-------|------|
| Varsayılan story durumu | BR-01 | CreateUserStoryAction | — |
| Backlog sıralaması | BR-02 | ReorderBacklogAction | — |
| Rol bazlı puanlama | BR-03 | EstimateStoryAction | 422 (geçersiz rol) |
| Toplam puan hesaplama | BR-04 | CalculateStoryPointsAction | — |
| Tek aktif sprint | BR-05 | SprintService.start() | 422 |
| Sprint planlama akışı | BR-06 | SprintService | — |
| Sprint tarih kuralları | BR-07 | CreateSprintRequest (validation) | 422 |
| Sprint kapatma | BR-08 | SprintService.close() | — |
| Scope change algılama | BR-09 | DetectScopeChangeAction | — |
| Scope change burndown | BR-10 | BurndownService | — |
| Epic tamamlanma | BR-11 | CalculateEpicCompletionAction | — |
| Tekil üyelik | BR-12 | AddMemberAction | 422 |
| Maksimum üye limiti | BR-12.1 | AddMemberAction | 422 |
| Otomatik owner üyelik | BR-13 | ProjectService.create() | — |
| Owner çıkarılamaz | BR-14 | MembershipPolicy | 403 |
| Proje silme cascade | BR-15 | ProjectService.delete() | — |
| Task atanmadan başlamaz | BR-16 | ChangeTaskStatusAction | 422 |
| Task düzenleme/durum yetkisi | BR-17 | TaskPolicy | 403 |
| Issue varsayılan değerler | BR-18 | CreateIssueAction | — |
| Member issue yetkisi | BR-19 | IssuePolicy | 403 |

---

**Önceki:** [03-STATE_MACHINE.md](./03-STATE_MACHINE.md)
**Sonraki:** [05-RBAC_PERMISSIONS.md](./05-RBAC_PERMISSIONS.md)
