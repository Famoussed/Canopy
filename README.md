# Canopy

> Şirketler için tasarlanmış, WebSocket tabanlı **gerçek zamanlı Scrum & proje yönetim sistemi**.
> Backlog, sprint, kanban board, epic, issue takibi ve burndown/velocity analitiği — hepsi canlı güncellenen bir arayüzde.

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)
![Reverb](https://img.shields.io/badge/Reverb-WebSockets-FF2D20?logo=laravel&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)
![Tests](https://img.shields.io/badge/tests-49%20dosya-success)

---

## Ne Yapıyor

Canopy, ekiplerin Scrum sürecini tek yerden yürütmesi için yazıldı:

- **Proje ve üyelik** — slug tabanlı projeler, rol bazlı üyelik (`ProjectRole`), üye limiti
- **Backlog** — epic → user story → task hiyerarşisi, story point tahmini
- **Sprint yönetimi** — sprint açma/kapama, kapsam değişikliği (scope change) kaydı, tek aktif sprint kuralı
- **Kanban board** — sürükle-bırak durum geçişleri, geçersiz geçişlerde domain exception
- **Issue takibi** — tip, öncelik, önem derecesi ve durum enum'larıyla
- **Analitik** — burndown ve velocity grafikleri
- **Gerçek zamanlı** — Laravel Reverb (WebSocket) üzerinden canlı board/bildirim güncellemesi
- **Bildirimler** — uygulama içi bildirim merkezi
- **Dosya ekleri** — S3 uyumlu depolama (Flysystem)
- **Aktivite günlüğü** — kim neyi ne zaman değiştirdi

---

## Mimari

```
Livewire Component  (view + etkileşim)
   └── Service      (orkestrasyon, transaction, iş kuralları)
        └── Action  (tek bir işi yapan yeniden kullanılabilir birim)
             └── Eloquent Model
```

Katman disiplini bilinçli olarak sıkı tutuldu:

- **Livewire bileşenleri iş mantığı taşımaz** — her şey Service'e delege edilir.
- **Domain kuralları exception'a bağlanmıştır** — `InvalidStatusTransitionException`,
  `ActiveSprintAlreadyExistsException`, `MaxMembersExceededException`,
  `OwnerCannotBeRemovedException`, `DuplicateMemberException`, `TaskNotAssignedException`.
  Geçersiz bir durum sessizce yutulmaz, tipli bir hata olarak yüzeye çıkar.
- **Durumlar native PHP enum'ları** — `IssueStatus`, `IssuePriority`, `IssueSeverity`,
  `IssueType`, `SprintStatus`, `StoryStatus`, `TaskStatus`, `ProjectRole`.
- **Olaylar transaction'dan sonra** dispatch edilir; broadcasting bunların üzerine kuruludur.
- **Yetkilendirme Policy'lerde**, Service'in içinde değil.

### Klasör yapısı

```
app/
├── Actions/        # Analytics · Auth · File · Issue · Notification · Project · Scrum
├── Enums/          # durum ve rol enum'ları
├── Events/         # Issue · Notification · Project · Scrum (broadcast edilenler dahil)
├── Exceptions/     # domain exception'ları
├── Livewire/       # sayfa bileşenleri + Forms
├── Models/         # Project, Epic, UserStory, Sprint, Task, Issue, ...
├── Policies/
└── Services/       # 12 servis: Project, Sprint, UserStory, Task, Issue, Burndown, Velocity, ...
tests/
├── Feature/        # Auth · Project · Scrum · Issues · Rbac · Broadcasting · Analytics · File
├── Livewire/       # bileşen testleri
└── Unit/           # Action ve analitik birim testleri
```

---

## Teknoloji Yığını

| Katman | Teknoloji |
|---|---|
| Framework | Laravel 11 · PHP 8.4 |
| UI | Livewire 4 + Flux UI + Tailwind CSS 4 |
| Gerçek zamanlı | Laravel Reverb + Laravel Echo |
| Kuyruk / önbellek | Redis (Predis) |
| Dosya depolama | Flysystem — AWS S3 |
| Test | PHPUnit 11 |
| Kod stili | Laravel Pint |
| Ortam | Docker + docker-compose |

---

## Kurulum

```bash
git clone https://github.com/Famoussed/Canopy.git
cd Canopy

composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Geliştirme sunucusu, Vite, kuyruk ve WebSocket sunucusunu birlikte çalıştır:

```bash
php artisan serve      # uygulama
npm run dev            # Vite
php artisan reverb:start   # WebSocket sunucusu
php artisan queue:work     # kuyruk
```

Docker ile:

```bash
docker compose up -d
```

---

## Testler

```bash
php artisan test
./vendor/bin/pint        # kod stili
```

Test kapsamı: kimlik doğrulama, proje ve üyelik (RBAC), Scrum akışları (sprint, story,
task, epic), issue yönetimi, broadcasting, analitik hesapları ve dosya eklerini içerir.

---

## Rota Haritası

```
/login · /register · /dashboard
/projects/create
/projects/{project:slug}            → proje panosu
/projects/{project:slug}/backlog    → backlog
/projects/{project:slug}/board      → kanban
/projects/{project:slug}/sprints    → sprint listesi
/projects/{project:slug}/epics      → epic listesi
/projects/{project:slug}/stories/{story}
/projects/{project:slug}/issues
/projects/{project:slug}/analytics  → burndown & velocity
/projects/{project:slug}/settings
```

Proje kapsamındaki tüm rotalar `project.member` middleware'i arkasındadır.

---

## Lisans

MIT

## İletişim

**Ahmet Selim Çiftci** — [GitHub](https://github.com/Famoussed) · [LinkedIn](https://www.linkedin.com/in/ahmet-selim-çiftci-51472035b)
