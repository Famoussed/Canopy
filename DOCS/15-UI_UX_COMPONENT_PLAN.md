# Canopy UI/UX & Livewire Component Mimarisi Planı

## TL;DR

Canopy'nin tüm frontend katmanını, **Laravel 11 + Livewire 4 + Flux v2 (KRİTİK & ZORUNLU) + Tailwind CSS v4 + Alpine.js + SortableJS** üzerine inşa edecek kapsamlı bir UI/UX mimari planıdır. İndigo-mavi renk paleti, üst navbar + daraltılabilir sol sidebar navigasyonu, full-page detay sayfaları ve SortableJS tabanlı drag-and-drop ile modern bir SaaS deneyimi hedeflenir. Backend zaten tamamen inşa edilmiş olup (13 model, 24 action, 12 service, tam RBAC), bu plan yalnızca sunucu tarafı render edilen reaktif Livewire arayüzüne odaklanır.

> **⚠️ KRİTİK: Flux v2 UI kullanımı ZORUNLUDUR. Tüm UI elemanları (button, input, modal, dropdown, navbar, sidebar, table, badge, card vb.) Flux bileşenleri (`<flux:button>`, `<flux:input>`, `<flux:modal>` vb.) kullanılarak oluşturulmalıdır. Custom Blade component yazmadan önce mutlaka Flux'ta karşılığı olup olmadığı kontrol edilmelidir.**

---

## 0. Zorunlu Teknoloji Stack'i (MANDATORY)

| Teknoloji | Versiyon | Paket | Durum |
|-----------|----------|-------|-------|
| **Livewire** | v4.x | `livewire/livewire` | ✅ ZORUNLU — Tüm reaktif bileşenler Livewire 4 ile yazılmalı |
| **Flux UI** | v2.x | `livewire/flux` | ✅ ZORUNLU — Tüm UI atomları Flux bileşenleri kullanılmalı |
| **Tailwind CSS** | v4.x | `@tailwindcss/vite` | ✅ ZORUNLU — Vite plugin olarak, CSS-first config |
| **Alpine.js** | Livewire 4 ile gömülü | — | ✅ Livewire tarafından otomatik dahil |
| **SortableJS** | npm | `sortablejs` | ✅ Drag-and-drop için zorunlu |
| **Laravel Echo** | v2.x | `laravel-echo` | ✅ WebSocket real-time güncellemeler |

### Neden Flux v2?

1. **Livewire-native bileşenler:** Button, Input, Select, Modal, Dropdown, Navbar, Sidebar, Table, Badge, Card, Tooltip, Breadcrumbs — hepsi Livewire ile tam entegre
2. **Tutarlı tasarım dili:** Tüm bileşenler birbiriyle uyumlu, renk/tipografi/spacing tutarlı
3. **`<flux:*>` prefix:** Flux bileşenleri `<flux:button>`, `<flux:modal>`, `<flux:sidebar>` şeklinde kullanılır
4. **Tema desteği:** `--color-accent` CSS variable ile global renk teması uygulanır
5. **Yeniden icat etme YOK:** Flux'ta var olan bileşenler kesinlikle custom yazılmamalı

### Flux Bileşen Kullanım Kuralları

- ✅ `<flux:button variant="primary">Kaydet</flux:button>` — DOĞRU
- ❌ `<button class="bg-indigo-600 ...">Kaydet</button>` — YANLIŞ (Flux varken custom yazmayın)
- ✅ `<flux:modal name="confirm-delete">` — DOĞRU
- ❌ Custom Alpine.js modal component — YANLIŞ
- ✅ `<flux:input wire:model="title" label="Başlık" />` — DOĞRU
- ❌ `<input type="text" class="border ...">` — YANLIŞ

### Mevcut Flux Bileşenleri (Kullanılabilir)

| Kategori | Flux Bileşenleri |
|----------|-----------------|
| **Layout** | `<flux:sidebar>`, `<flux:navbar>`, `<flux:main>`, `<flux:header>`, `<flux:heading>`, `<flux:container>` |
| **Navigasyon** | `<flux:navlist>`, `<flux:navmenu>`, `<flux:breadcrumbs>`, `<flux:link>` |
| **Form** | `<flux:input>`, `<flux:textarea>`, `<flux:select>`, `<flux:checkbox>`, `<flux:switch>`, `<flux:radio>`, `<flux:field>`, `<flux:fieldset>`, `<flux:label>`, `<flux:error>` |
| **Eylem** | `<flux:button>`, `<flux:dropdown>`, `<flux:menu>`, `<flux:modal>`, `<flux:tooltip>` |
| **Veri** | `<flux:table>`, `<flux:badge>`, `<flux:card>`, `<flux:avatar>`, `<flux:separator>`, `<flux:skeleton>` |
| **Geri Bildirim** | `<flux:callout>`, `<flux:progress>` |
| **Diğer** | `<flux:icon>`, `<flux:brand>`, `<flux:profile>`, `<flux:spacer>`, `<flux:pagination>` |

---

## 1. Tasarım Sistemi (Design System)

### 1.1 Renk Paleti (Indigo-Mavi SaaS Teması)

| Token | Kullanım | Tailwind Değeri |
|-------|----------|-----------------|
| **Primary** | Butonlar, aktif linkler, seçili tab | `indigo-600` (hover: `indigo-700`) |
| **Primary Light** | Badge arka planı, hover state | `indigo-50`, `indigo-100` |
| **Surface** | Sayfa arka planı | `gray-50` |
| **Card** | Kart / panel arka planı | `white` |
| **Sidebar BG** | Sol sidebar arka plan | `gray-900` |
| **Sidebar Text** | Sidebar menü metni | `gray-300` (hover: `white`) |
| **Text Primary** | Ana metin | `gray-900` |
| **Text Secondary** | Yardımcı metin, label | `gray-500` |
| **Text Muted** | Placeholder, devre dışı | `gray-400` |
| **Border** | Kart kenarlıkları, ayırıcılar | `gray-200` |
| **Success** | Done durumu, başarı mesajı | `emerald-500` |
| **Warning** | In Progress, dikkat mesajları | `amber-500` |
| **Danger** | Hata, silme, kritik seviye | `red-500` |
| **Info** | Bilgi badge, yeni durum | `sky-500` |

**Durum renkleri (Enum Color Mapping):**

| Durum | Renk | Tailwind |
|-------|------|----------|
| `New` | Mavi | `bg-sky-100 text-sky-700` |
| `InProgress` | Sarı | `bg-amber-100 text-amber-700` |
| `Done` | Yeşil | `bg-emerald-100 text-emerald-700` |
| `Planning` (Sprint) | Gri | `bg-gray-100 text-gray-700` |
| `Active` (Sprint) | İndigo | `bg-indigo-100 text-indigo-700` |
| `Closed` (Sprint) | Koyu gri | `bg-gray-200 text-gray-600` |

**Issue Priority renkleri:** `Low` → `gray-400`, `Normal` → `amber-500`, `High` → `red-500`
**Issue Severity renkleri:** `Wishlist` → `gray-400`, `Minor` → `amber-500`, `Critical` → `red-600`

### 1.2 Tipografi

| Element | Font | Size | Weight | Tailwind |
|---------|------|------|--------|----------|
| Sayfa başlığı | Figtree (Inter fallback) | 24px | Bold | `text-2xl font-bold` |
| Section başlığı | Figtree | 18px | Semibold | `text-lg font-semibold` |
| Kart başlığı | Figtree | 14px | Medium | `text-sm font-medium` |
| Gövde metin | Figtree | 14px | Normal | `text-sm` |
| Küçük metin / label | Figtree | 12px | Medium | `text-xs font-medium` |
| Badge / tag | Figtree | 11px | Medium | `text-[11px] font-medium` |

### 1.3 Spacing & Grid Kuralları

| Kural | Değer |
|-------|-------|
| Sayfa padding | `px-6 py-6` (mobil: `px-4 py-4`) |
| Kartlar arası boşluk | `gap-4` |
| Kart iç padding | `p-4` |
| Section arası boşluk | `space-y-6` |
| Button iç padding | `px-4 py-2` (sm: `px-3 py-1.5`) |
| Form elemanları arası | `space-y-4` |
| Border radius (kartlar) | `rounded-lg` |
| Border radius (butonlar) | `rounded-md` |
| Border radius (badge) | `rounded-full` |
| Gölge (kartlar) | `shadow-sm` |
| Gölge (dropdown) | `shadow-lg` |

### 1.4 Yeniden Kullanılabilir UI Atomları (Flux v2 Bileşenleri)

> **⚠️ KRİTİK: Aşağıdaki bileşenler Flux'tan gelir. Custom Blade component yazmak YERİNE Flux bileşenlerini kullanın.**

| İhtiyaç | Flux Bileşeni | Kullanım Örneği |
|---------|---------------|-----------------|
| Buton | `<flux:button>` | `<flux:button variant="primary" wire:click="save">Kaydet</flux:button>` |
| Badge / Durum | `<flux:badge>` | `<flux:badge color="emerald" size="sm">Done</flux:badge>` |
| Kart | `<flux:card>` | `<flux:card class="p-4">...</flux:card>` |
| Modal | `<flux:modal>` | `<flux:modal name="confirm-delete" class="max-w-sm">...</flux:modal>` |
| Dropdown | `<flux:dropdown>` | `<flux:dropdown><flux:button icon="ellipsis-horizontal" /><flux:menu>...</flux:menu></flux:dropdown>` |
| Avatar | `<flux:avatar>` | `<flux:avatar src="{{ $user->avatar }}" />` |
| Durum Badge | `<flux:badge>` | Enum `color()` metodu ile renk otomatik seçilir |
| Boş Durum | Custom: `<x-empty-state>` | Flux'ta yok → minimal Blade component |
| Toast | Flux `<flux:callout>` + Alpine.js | Sağ üstte geçici bildirim |
| Onay Dialogu | `<flux:modal>` | `<flux:modal name="confirm" class="max-w-sm">Emin misiniz?</flux:modal>` |
| Yükleniyor | `<flux:skeleton>` + `wire:loading` | `<flux:button wire:loading.attr="disabled">` |
| Breadcrumb | `<flux:breadcrumbs>` | Sayfa hiyerarşisi gösterimi |
| Stat Kart | Custom: `<x-stat-card>` | Dashboard metrikleri için (Flux card + custom) |
| Tablo | `<flux:table>` | Issue listesi, üye listesi vb. |
| İkon | `<flux:icon>` | `<flux:icon name="arrow-up" class="text-red-500" />` |
| Tooltip | `<flux:tooltip>` | `<flux:tooltip content="Bilgi">...</flux:tooltip>` |
| Separator | `<flux:separator>` | İçerik bölücü çizgi |
| Skeleton | `<flux:skeleton>` | Lazy loading placeholder |

**Sadece Flux'ta karşılığı OLMAYAN bileşenler custom yazılır:**
- `<x-empty-state>` — Boş liste durumu: ikon + mesaj + aksiyon butonu
- `<x-stat-card>` — Dashboard metrikleri için kart (label, value, icon, trend)
- `<x-status-badge>` — Enum instance alır, Flux badge ile doğru renk+label üretir
- `<x-priority-icon>` — Ok ikonuyla priority gösterimi (Flux icon wrapper)

---

## 2. Sayfa Haritası & Navigasyon Yapısı

### 2.1 Layout Yapısı

```
┌────────────────────────────────────────────────────────────────┐
│  TOP NAVBAR (h-16, white, border-b, shadow-sm)                │
│  [☰ Toggle] [Canopy Logo] ──── [Search] ──── [🔔 3] [Avatar] │
├──────────┬─────────────────────────────────────────────────────┤
│ SIDEBAR  │  MAIN CONTENT AREA                                  │
│ (w-64    │  (flex-1, bg-gray-50, overflow-y-auto)             │
│ daraltı- │                                                     │
│ labilir  │  ┌─ Breadcrumb ──────────────────────┐             │
│ w-16)    │  │ Proje > Backlog > US-42            │             │
│          │  └────────────────────────────────────┘             │
│ [Proje]  │                                                     │
│ [Backlog]│  ┌─ Page Header ─────────────────────┐             │
│ [Kanban] │  │ Başlık            [+ Yeni] [Filtre]│             │
│ [Sprint] │  └────────────────────────────────────┘             │
│ [Issues] │                                                     │
│ [Analiz] │  ┌─ Content Area ────────────────────┐             │
│ [Ayarlar]│  │                                    │             │
│          │  │  (Sayfa içeriği burada)             │             │
│          │  │                                    │             │
│          │  └────────────────────────────────────┘             │
└──────────┴─────────────────────────────────────────────────────┘
```

**Sidebar davranışı:**
- Geniş mod (`w-64`): İkon + metin label gösterir
- Daraltılmış mod (`w-16`): Sadece ikon, hover'da tooltip
- Toggle butonu üst navbar sol tarafında
- Mobilde: Sidebar tamamen gizli, hamburger menü ile overlay açılır
- Sidebar state `localStorage`'da saklanır (Alpine.js `$persist`)

### 2.2 Sayfa Rotaları (Web Routes)

| Rota | Sayfa | Livewire Component |
|------|-------|--------------------|
| `/login` | Giriş | `Auth.Login` |
| `/register` | Kayıt | `Auth.Register` |
| `/dashboard` | Ana panel (proje listesi) | `Dashboard` |
| `/projects/create` | Yeni proje formu | `Projects.CreateProject` |
| `/projects/{slug}` | Proje Dashboard | `Projects.ProjectDashboard` |
| `/projects/{slug}/backlog` | Scrum Backlog | `Scrum.Backlog` |
| `/projects/{slug}/board` | Kanban Sprint Board | `Scrum.KanbanBoard` |
| `/projects/{slug}/sprints` | Sprint listesi | `Scrum.SprintList` |
| `/projects/{slug}/sprints/{sprint}` | Sprint detay | `Scrum.SprintDetail` |
| `/projects/{slug}/epics` | Epic listesi | `Scrum.EpicList` |
| `/projects/{slug}/epics/{epic}` | Epic detay | `Scrum.EpicDetail` |
| `/projects/{slug}/stories/{story}` | User Story detay (full-page) | `Scrum.StoryDetail` |
| `/projects/{slug}/issues` | Issue listesi | `Issues.IssueList` |
| `/projects/{slug}/issues/{issue}` | Issue detay (full-page) | `Issues.IssueDetail` |
| `/projects/{slug}/analytics` | Analytics dashboard | `Analytics.AnalyticsDashboard` |
| `/projects/{slug}/settings` | Proje ayarları & üyeler | `Projects.ProjectSettings` |

---

## 3. Temel Kullanıcı Akışları (User Flows)

### 3.1 Akış: Kayıt → Proje Oluştur → Story Ekle → Sprint'e Taşı

**Adım 1 — Kayıt (`/register`)**
- Kullanıcı `name`, `email`, `password`, `password_confirmation` alanlarını doldurur
- `wire:submit` ile `Auth.Register` component'inin `register()` metodunu tetikler
- Başarılı → `redirect('/dashboard')` ile ana panele yönlenir
- Hatalı → Alanların altında inline hata mesajları (`@error` directive)

**Adım 2 — Dashboard (`/dashboard`)**
- Kullanıcının üye olduğu projeler `grid` layout'ta kartlar halinde listelenir
- Her kartta: proje adı, açıklama (truncated), üye sayısı, kullanıcının rolü (badge)
- Sağ üstte `+ Yeni Proje` butonu → `/projects/create` sayfasına `wire:navigate`

**Adım 3 — Proje Oluştur (`/projects/create`)**
- Form alanları: `name`, `description`
- Submit → `CreateProject` component → `ProjectService::create()` çağırır
- Başarılı → `redirect("/projects/{slug}/backlog")`
- Kullanıcı otomatik olarak `Owner` rolüyle atanır (BR-13)

**Adım 4 — Backlog görünümü (`/projects/{slug}/backlog`)**
- Sayfa yüklendiğinde `sprint_id=null` olan tüm User Story'ler `order` sırasına göre listelenir
- Her story satırında: sıra, başlık, epic badge (renkli), durum badge, toplam puan, assigned kullanıcı avatarı
- Sağ üstte `+ Yeni Story` butonu → Sayfanın altında veya üstünde bir inline form alanı açılır (toggle ile)
- Kullanıcı `title` yazar → `wire:submit` ile `createStory()` çağrılır → Story backlog'un en altına eklenir → Liste `wire:key` ile güncellenir

**Adım 5 — Story'yi Sprint'e taşıma**
- Backlog listesinde her story satırının sağında `⋮` dropdown menüsü (Alpine.js) bulunur
- "Sprint'e Taşı" seçeneğine tıklayınca bir mini-dropdown veya modal açılır: mevcut `Planning` ve `Active` sprint'ler listelenir
- Sprint seçilir → `moveToSprint($storyId, $sprintId)` çağrılır → `MoveStoryToSprintAction` tetiklenir
- Eğer sprint `Active` ise scope change otomatik kaydedilir (BR-09)
- Story backlog listesinden kaybolur, toast ile onay mesajı gösterilir

**Alternatif — Sürükle-bırak ile taşıma:**
- Backlog sayfasında sağ tarafta aktif sprint'in mini bir kartı gösterilir
- Kullanıcı story'yi SortableJS ile bu alana sürükler → aynı `moveToSprint()` tetiklenir

### 3.2 Akış: Kanban Board üzerinde Task yönetimi

**Adım 1** — `/projects/{slug}/board` sayfası açılır
**Adım 2** — Üstte aktif sprint seçici (tek aktif sprint varsa otomatik seçili)
**Adım 3** — 3 kolon gösterilir: `New` | `In Progress` | `Done`
**Adım 4** — Her kolonda o duruma sahip story kartları, altlarında task'lar listelenir
**Adım 5** — Kullanıcı bir task kartını sürükleyerek kolonlar arası taşır → `ChangeTaskStatusAction` tetiklenir
**Adım 6** — Atanmamış task `In Progress`'e taşınırsa → hata toast gösterilir (BR-16)
**Adım 7** — Kart üzerinde inline tıklama ile assign dropdown açılır, üye seçilir

---

## 4. Livewire Component Mimarisi & Hiyerarşisi

### 4.1 Layout Components

| Component | Dosya Yolu | Sorumluluk | State |
|-----------|-----------|------------|-------|
| `AppLayout` | `layouts/app.blade.php` | Ana layout: navbar + sidebar + slot | — |
| `Navbar` | `Livewire/Layout/Navbar.php` | Üst bar: logo, arama, bildirim dropdown, kullanıcı menüsü | `$unreadCount` (WebSocket dinler) |
| `Sidebar` | `Livewire/Layout/Sidebar.php` | Sol menü: proje modülleri, daralt/genişlet | `$collapsed` (localStorage via Alpine) |
| `NotificationDropdown` | `Livewire/Layout/NotificationDropdown.php` | Bildirim listesi, okundu işaretle | `$notifications`, `$unreadCount` |

### 4.2 Auth Components

| Component | Dosya Yolu | Sorumluluk |
|-----------|-----------|------------|
| `Login` | `Livewire/Auth/Login.php` | Login formu, validation, redirect |
| `Register` | `Livewire/Auth/Register.php` | Register formu, validation, redirect |

### 4.3 Dashboard

| Component | Sorumluluk | State |
|-----------|------------|-------|
| `Dashboard` | Proje listesi grid, boş durum yönetimi | `$projects` |
| `ProjectCard` *(Blade)* | Tek proje kartı render | Props: `$project` |

### 4.4 Project Module

| Component | Sorumluluk | State | İlişki |
|-----------|------------|-------|--------|
| `CreateProject` | Yeni proje formu | `$name`, `$description` | — |
| `ProjectDashboard` | Proje özet sayfası: son aktiviteler, sprint özeti, istatistikler | `$project`, `$stats` | Parent |
| `ProjectSettings` | Proje bilgileri düzenleme + üye yönetimi | `$project` | Parent |
| `MemberManager` | Üye listesi, ekleme, rol değiştirme, çıkarma | `$members`, `$newEmail`, `$newRole` | Child of ProjectSettings |
| `ActivityFeed` | Son aktiviteler timeline | `$activities` | Child (ProjectDashboard, StoryDetail) |

### 4.5 Scrum Module (En Kritik)

| Component | Sorumluluk | State | İlişki |
|-----------|------------|-------|--------|
| **`Backlog`** | Backlog ana sayfası: story listesi + sağda sprint paneli | `$stories`, `$sprints`, `$filters` | **Page-level parent** |
| `BacklogStoryRow` *(Blade)* | Tek bir story satırı: başlık, badge'ler, aksiyonlar | Props: `$story` | Blade partial |
| `CreateStoryForm` | Inline story oluşturma formu (toggle ile açılır) | `$title`, `$epicId`, `$showForm` | Child of Backlog |
| **`KanbanBoard`** | Sprint Kanban board: 3 kolon | `$sprint`, `$columns`, `$stories` | **Page-level parent** |
| `KanbanColumn` *(Blade)* | Tek bir durum kolonu | Props: `$status`, `$stories` | Blade partial |
| `KanbanStoryCard` *(Blade)* | Story kartı: başlık, task sayısı, puan | Props: `$story` | Blade partial |
| `KanbanTaskCard` *(Blade)* | Task kartı: başlık, atanan kişi, durum | Props: `$task` | Blade partial |
| **`SprintList`** | Sprint listesi sayfası | `$sprints` | Page-level |
| **`SprintDetail`** | Sprint detay: bilgiler + stories + burndown | `$sprint` | Page-level parent |
| `SprintActions` | Sprint başlat/kapat butonları | `$sprint` | Child of SprintDetail |
| **`EpicList`** | Epic listesi, ilerleme çubukları | `$epics` | Page-level |
| **`EpicDetail`** | Epic detay: bilgi + bağlı stories | `$epic`, `$stories` | Page-level |
| **`StoryDetail`** | Full-page story detay | `$story`, `$tasks` | **Page-level parent** |
| `StoryInfo` | Story metadata: durum, epic, puan, atanan | `$story` | Child of StoryDetail |
| `TaskList` | Story'ye bağlı task listesi | `$tasks` | Child of StoryDetail |
| `TaskRow` *(Blade)* | Tek task satırı: checkbox, başlık, assignee | Props: `$task` | Blade partial |
| `CreateTaskForm` | Inline task oluşturma | `$title`, `$assignedTo` | Child of TaskList |
| `StoryPointsEditor` | Rol bazlı puan düzenleme tablosu | `$points`, `$roles` | Child of StoryDetail |
| `AttachmentList` | Dosya listesi + yükleme | `$attachments` | Child of StoryDetail |

### 4.6 Issue Module

| Component | Sorumluluk | State | İlişki |
|-----------|------------|-------|--------|
| **`IssueList`** | Issue listesi + filtreler | `$issues`, `$filters` | **Page-level parent** |
| `IssueFilterBar` | Tip, öncelik, ciddiyet, durum filtreleri | `$type`, `$priority`, `$severity`, `$status` | Child of IssueList |
| `CreateIssueForm` | Yeni issue formu (modal veya inline) | `$title`, `$type`, `$priority`, `$severity` | Child of IssueList |
| **`IssueDetail`** | Full-page issue detay | `$issue` | **Page-level** |
| `IssueInfo` | Issue metadata düzenleyicisi | `$issue` | Child of IssueDetail |

### 4.7 Analytics Module

| Component | Sorumluluk | State |
|-----------|------------|-------|
| **`AnalyticsDashboard`** | Analytics ana sayfa | `$project` |
| `BurndownChart` | Burndown grafiği (Chart.js/ApexCharts) | `$chartData` — `BurndownService` çağırır |
| `VelocityChart` | Velocity bar chart | `$velocityData` — `VelocityService` çağırır |
| `SprintSelector` | Burndown için sprint seçici | `$selectedSprint` |

### 4.8 Component İletişim Haritası (Events)

| Event Adı | Dispatch Eden | Dinleyen | Tetikleyici |
|-----------|---------------|----------|-------------|
| `story-created` | `CreateStoryForm` | `Backlog` | Yeni story oluşturulduğunda listeyi yenile |
| `story-moved` | `Backlog` | `Backlog` (self-refresh) | Story sprint'e taşındığında |
| `story-status-changed` | `KanbanBoard` | `KanbanBoard` (re-render), `SprintDetail` | Drag ile durum değişince |
| `task-created` | `CreateTaskForm` | `TaskList` | Yeni task oluşturulduğunda |
| `task-status-changed` | `TaskList` | `KanbanBoard`, `StoryDetail` | Task durumu değişince |
| `task-assigned` | `TaskList` | `KanbanBoard` | Task atandığında |
| `sprint-started` | `SprintActions` | `SprintList`, `KanbanBoard`, `Backlog` | Sprint başlatıldığında |
| `sprint-closed` | `SprintActions` | `SprintList`, `Backlog` | Sprint kapatıldığında |
| `member-updated` | `MemberManager` | `ProjectSettings` | Üye eklendi/silindi/rol değişti |
| `filters-changed` | `IssueFilterBar` | `IssueList` | Filtre değişikliğinde |
| `file-uploaded` | `AttachmentList` | `AttachmentList` (self) | Dosya yükleme sonrası |
| `notification-received` | WebSocket (Echo) | `NotificationDropdown`, `Navbar` | Yeni bildirim geldiğinde |

**WebSocket entegrasyonu:**
- `Navbar` component'i `mount()` içinde `getListeners()` ile `echo-private:user.{userId},NotificationSent` eventini dinler
- `KanbanBoard` component'i `echo-private:project.{projectId}` kanalını dinleyerek real-time board güncellemesi alır

---

## 5. Mikro-Etkileşimler & Performans

### 5.1 Loading States (`wire:loading`)

| Bileşen / Aksiyon | Loading Davranışı |
|--------------------|-------------------|
| Story oluşturma | Submit butonunda spinner: `wire:loading wire:target="createStory"` + buton disabled |
| Sprint başlatma | Buton metni "Başlatılıyor..." olur, `wire:loading.attr="disabled"` |
| Task durum değişikliği | Checkbox yerine mini spinner döner |
| Sayfa geçişleri | Üst-tarafta thin progress bar (`wire:navigate` otomatik sağlar) |
| Filtre uygulanması | Liste alanının üstüne `opacity-50` overlay + spinner: `wire:loading.class="opacity-50"` |
| Dosya yükleme | Progress bar gösterilir (Livewire `WithFileUploads` trait + `$this->progress`) |
| Üye ekleme | Buton disabled + spinner |
| Story sürükle-bırak | Taşınan kart `opacity-60 scale-95` efekti, bırakma alanında `ring-2 ring-indigo-400` highlight |

### 5.2 Optimistic UI Uygulamaları

| İşlem | Optimistic Davranış | Rollback |
|-------|---------------------|----------|
| Task status toggle (checkbox) | Tıklanınca anında UI güncellenir (Alpine.js `x-on:click` ile sınıf değişikliği) | Sunucu hata dönerse eski state'e `$refresh` ile geri dönülür |
| Backlog sıralama (drag) | Kartlar anında yeni pozisyona yerleşir (SortableJS) | Sunucu validasyonu başarısız olursa `$this->dispatch('$refresh')` |
| Kanban sürükle-bırak | Kart anında yeni kolona geçer | `InvalidStatusTransitionException` → toast hata + kartı eski kolona döndür |
| Bildirim okundu işaretle | Anında UI'da `read` sınıfı eklenir | — |

### 5.3 `wire:navigate` Kullanım Stratejisi

- Tüm `<a>` linkleri `wire:navigate` ile çalışır → sayfa geçişlerinde full-page reload yerine SPA-benzeri deneyim
- Layout'un navbar + sidebar'ı `@persist` direktifi ile korunur, sadece content alanı güncellenir
- Bu, sidebar collapse durumunun ve bildirim state'inin sayfa geçişlerinde korunmasını sağlar

### 5.4 Lazy Loading

| Bileşen | Strateji |
|---------|----------|
| `BurndownChart` | `lazy` mount — sayfa açılınca placeholder gösterir, chart verisi arka planda yüklenir |
| `VelocityChart` | `lazy` mount |
| `ActivityFeed` | `lazy` mount + infinite scroll (`wire:visible` ile sayfa sonuna gelinince yeni batch yükle) |
| `AttachmentList` | Normal mount (küçük veri seti) |
| `NotificationDropdown` | Dropdown açılınca yükle (`wire:init` değil, Alpine `x-show` true olunca `$wire.loadNotifications()`) |

### 5.5 Polling & Real-time

| Bileşen | Strateji |
|---------|----------|
| `KanbanBoard` | WebSocket (Echo) ile real-time — `StoryStatusChanged`, `TaskStatusChanged` eventlerine tepki verir |
| `Navbar` bildirim sayısı | WebSocket ile `NotificationSent` event dinleme |
| `BurndownChart` | Polling YOK — sadece sayfa açılışında ve sprint seçildiğinde çekilir |
| `Backlog` | WebSocket ile `StoryCreated` dinler (başka kullanıcı story eklerse) |

### 5.6 SortableJS Entegrasyonu (Alpine.js üzerinden)

Backlog ve Kanban board'da sürükle-bırak için:

**Alpine component yaklaşımı:**
- Her sürüklenebilir liste bir Alpine `x-data` component'i olur
- `x-init` içinde `new Sortable(el, { ... })` ile initialize edilir
- `onEnd` callback'inde `$wire.reorder(newOrder)` veya `$wire.moveTask(taskId, newStatus)` çağrılır
- `ghostClass: 'opacity-30'`, `dragClass: 'shadow-xl rotate-2'` ile görsel feedback verilir

**Kanban specifik:**
- Kolonlar arası sürükleme: `group: 'kanban'` ayarıyla tüm kolonlar aynı grupta olur
- `onAdd` callback'inde `$wire.changeTaskStatus(taskId, newColumnStatus)` tetiklenir

---

## 6. Sayfa Bazlı Detaylı UI Spesifikasyonları

### 6.1 Login / Register Sayfaları
- Ortalanmış kart (`max-w-md mx-auto mt-20`)
- Logo üstte, form alanları altında
- Validasyon: `wire:model.blur` ile alan bazlı kontrol
- "Hesabın yok mu? Kayıt ol" / "Hesabın var mı? Giriş yap" linkleri

### 6.2 Dashboard (Proje Listesi)
- Üstte: "Projelerim" başlık + sağda "Yeni Proje" butonu
- Grid layout: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`
- Her kartta: proje adı (bold), açıklama (2 satır truncate), üye sayısı, aktif sprint bilgisi, kullanıcının rolü badge
- Hiç proje yoksa: `<x-empty-state>` — ikon + "Henüz projeniz yok" mesajı + "Proje Oluştur" butonu

### 6.3 Backlog Sayfası
- Sol alan (%70): Story listesi — sıralı satırlar halinde, her satırda:
  - Sürükleme handle (`⋮⋮` ikon — SortableJS)
  - Story ID (kısa ref)
  - Başlık (tıklanınca `/projects/{slug}/stories/{story}` sayfasına `wire:navigate`)
  - Epic badge (renkli chip)
  - Durum badge
  - Toplam puan
  - Sağda `⋮` menü: "Sprint'e Taşı", "Düzenle", "Sil"
- Sağ alan (%30): Sprint paneli — mevcut sprint'ler listesi:
  - Planning sprint'ler: Story kabul eden drop alanı
  - Active sprint: story sayısı, toplam puan
  - `+ Yeni Sprint` butonu
- Üstte: Epic filtreleme dropdown + status filtreleme

### 6.4 Kanban Board
- Sprint seçici (üstte, eğer birden fazla sprint varsa)
- 3 kolon: `New` | `In Progress` | `Done`
- Her kolon header'ında: durum adı + kart sayısı badge
- Kart yapısı:
  - Story kartı (büyük, beyaz, `border-l-4` ile epic rengi)
    - Başlık, puan, task sayısı/tamamlanan task
    - Tıkla → story detay sayfası
  - Task kartları (story altında, küçük, gri arka plan)
    - Başlık, assignee avatar, sürükleme handle
- Kolonlar arası sürükleme SortableJS ile
- Boş kolon: "Bu kolonda henüz öğe yok" mesajı + hafif kesikli kenarlık

### 6.5 Story Detail (Full-page)
- Breadcrumb: `Proje > Backlog > US-42: Başlık`
- Sol alan (%65):
  - Başlık (inline editable — tıkla, düzenle, blur'da kaydet)
  - Açıklama (Markdown / textarea, inline editable)
  - Task listesi bölümü (TaskList component)
  - Aktivite feed'i (ActivityFeed component, lazy loaded)
- Sağ alan (%35 — sticky sidebar):
  - Durum değiştirme dropdown
  - Epic ataması dropdown
  - Sprint bilgisi
  - Story puanları (StoryPointsEditor)
  - Oluşturan kişi + tarih
  - Dosya ekleri (AttachmentList)

### 6.6 Issue List
- Tablo görünümü (`table` layout)
- Kolonlar: tip ikonu, başlık, öncelik, ciddiyet, durum, atanan, tarih
- Üstte filtre bar: tip, öncelik, ciddiyet, durum (her biri dropdown)
- Filtrelerin farklı kombinasyonları URL query param olarak saklanır
- Sağ üstte `+ Yeni Issue` butonu → inline form veya modal

### 6.7 Analytics Dashboard
- Üstte: stat kartları satırı (toplam story, tamamlanan, aktif sprint, velocity)
- Alt sol: Burndown Chart (Chart.js line chart, ideal vs actual çizgiler)
- Alt sağ: Velocity Chart (bar chart, son 5 sprint)
- Sprint seçici: Burndown için hangi sprint gösterileceğini seçer

---

## 7. Adım Adım Uygulama Sırası (Implementation Order)

1. ~~`tailwind.config.js` dosyasını genişlet~~ → **TAMAMLANDI:** Tailwind v4 + Flux v2 kurulu, `resources/css/app.css` içinde `@theme` ile renk/font tanımı yapıldı

2. **Flux entegrasyonu tamamlandı:** `@fluxAppearance` ve `@fluxScripts` directive'leri layout'a eklenecek. Custom Blade component ihtiyacı minimale indi — sadece `<x-empty-state>`, `<x-stat-card>`, `<x-status-badge>`, `<x-priority-icon>` oluşturulacak

3. Ana layout'u oluştur: `resources/views/components/layouts/app.blade.php` — Flux `<flux:sidebar>` + `<flux:navbar>` + `<flux:main>` + `@persist` alanları

4. `Layout/Sidebar` Livewire component'i — `<flux:sidebar collapsible>` ile, `<flux:navlist>` menü öğeleri

5. Auth sayfaları: `Auth/Login` ve `Auth/Register` — Flux `<flux:input>`, `<flux:button>` ile

6. `Dashboard` component: Flux `<flux:card>`, proje listesi, boş durum

7. `Projects/CreateProject` formu — Flux form bileşenleri ile

8. `Projects/ProjectSettings` + `MemberManager` — Flux `<flux:table>`, `<flux:modal>` ile

9. **Scrum Backlog** (kritik): `Scrum/Backlog` + SortableJS + Flux bileşenleri

10. **Story Detail**: `Scrum/StoryDetail` full-page + Flux form/card/badge bileşenleri

11. **Kanban Board**: `Scrum/KanbanBoard` + SortableJS + WebSocket

12. Sprint CRUD: `Scrum/SprintList`, `Scrum/SprintDetail`

13. Epic yönetimi: `Scrum/EpicList`, `Scrum/EpicDetail`

14. **Issue Module**: `Issues/IssueList` (Flux `<flux:table>`) + filtreleme + `Issues/IssueDetail`

15. **Analytics**: `Analytics/AnalyticsDashboard` + Chart.js

16. Bildirim sistemi + WebSocket (Echo) bağlantısı

17. Global `wire:navigate` geçişleri, `@persist` optimizasyonları, lazy loading

---

## 8. Teknik Kararlar Özeti

| Karar | Seçim | Gerekçe |
|-------|-------|---------|
| **UI Kütüphanesi** | **Flux v2 (ZORUNLU)** | Livewire-native, tutarlı tasarım, 50+ bileşen |
| Livewire Versiyonu | Livewire 4 | En güncel, islands, async, SFC desteği |
| Tailwind CSS Versiyonu | Tailwind CSS v4 | Flux v2 gereksinimleri, CSS-first config |
| Renk paleti | İndigo-Mavi Modern SaaS (`--color-accent: indigo-600`) | Kullanıcı tercihi, profesyonel görünüm |
| Navigasyon | `<flux:navbar>` + `<flux:sidebar collapsible>` | Flux native, responsive, daraltılabilir |
| Detay görünümü | Full-page detay sayfaları | Daha fazla alan, complex içerik için uygun |
| Drag & Drop | SortableJS | Livewire + Alpine.js ile doğal entegrasyon, hafif |
| Chart kütüphanesi | Chart.js | Hafif boyut, Alpine.js ile kolay entegre |
| Inline edit | Alpine.js toggle + `wire:model.blur` | Anlık kaydetme, SPA-benzeri deneyim |
| Form bileşenleri | `<flux:input>`, `<flux:select>`, `<flux:textarea>` | Flux native, validation entegrasyonu |
| Modal / Dialog | `<flux:modal>` | Flux native, Alpine.js entegrasyonu |
| Tablo | `<flux:table>` | Issue ve üye listesi için |
| Real-time | Laravel Echo + Reverb WebSocket | Mevcut altyapı, Livewire native destek |
| Sayfa geçişleri | `wire:navigate` + `@persist` | SPA deneyimi, layout korunması |
