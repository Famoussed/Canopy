# Canopy - Laravel Reverb Kullanımı (Detaylı Rehber)

> Bu döküman, Canopy projesinde Laravel Reverb'ün nerelerde ve nasıl kullanıldığını, her bir syntax'ın ne işe yaradığını junior bir developer'ın bile anlayabileceği şekilde detaylıca açıklamaktadır.

---

## İçindekiler

1. [Laravel Reverb Nedir?](#1-laravel-reverb-nedir)
2. [WebSocket Nedir?](#2-websocket-nedir)
3. [Reverb Mimarisi: Büyük Resim](#3-reverb-mimarisi-büyük-resim)
4. [Yapılandırma Dosyaları](#4-yapılandırma-dosyaları)
5. [Broadcasting Altyapısı](#5-broadcasting-altyapısı)
6. [Kanal (Channel) Sistemi](#6-kanal-channel-sistemi)
7. [Event Sınıfları ve Broadcast Syntax'ı](#7-event-sınıfları-ve-broadcast-syntaxı)
8. [Tüm Broadcast Event'leri (Detaylı)](#8-tüm-broadcast-eventleri-detaylı)
9. [Service Katmanında Event Dispatch](#9-service-katmanında-event-dispatch)
10. [Frontend: Laravel Echo Entegrasyonu](#10-frontend-laravel-echo-entegrasyonu)
11. [Listener'lar ve Broadcasting Zinciri](#11-listenerlar-ve-broadcasting-zinciri)
12. [Hata Yönetimi](#12-hata-yönetimi)
13. [Veri Akış Diyagramları](#13-veri-akış-diyagramları)
14. [Özet: Nerede, Ne, Nasıl?](#14-özet-nerede-ne-nasıl)

---

## 1. Laravel Reverb Nedir?

**Laravel Reverb**, Laravel'in kendi WebSocket sunucusudur. Geleneksel HTTP'de istemci (tarayıcı) her zaman sunucuya istek atar ve yanıt alır. Ama bazen sunucunun istemciye **anlık bilgi göndermesi** gerekir — örneğin:

- Birisi projeye yeni bir issue eklediğinde, ekrandaki listeyi güncellemek
- Bir görev birine atandığında, o kişiye bildirim göndermek
- Sprint başlatıldığında, tüm takımı bilgilendirmek

Reverb bu tür **anlık, iki yönlü iletişimi** sağlar.

### Neden Reverb (Alternatifler Yerine)?

| Özellik | Reverb | Pusher | Socket.io |
|---------|--------|--------|-----------|
| Barındırma | Kendi sunucun (self-hosted) | Üçüncü taraf (SaaS) | Kendi sunucun |
| Maliyet | Ücretsiz | Ücretli (mesaj başı) | Ücretsiz |
| Laravel Entegrasyonu | Native (doğal) | İyi | Manuel |
| Kurulum | `composer require laravel/reverb` | API key gerekli | Ayrı Node.js sunucu |

---

## 2. WebSocket Nedir?

Bunu basitçe bir **telefon hattı** gibi düşünebilirsin:

```
Normal HTTP (Mektup gibi):
  Tarayıcı: "Merhaba, yeni issue var mı?" → Sunucu: "Hayır."
  Tarayıcı: "Merhaba, yeni issue var mı?" → Sunucu: "Hayır."
  Tarayıcı: "Merhaba, yeni issue var mı?" → Sunucu: "Evet! İşte veri."
  (Her seferinde yeni bağlantı açılır, verimsiz)

WebSocket (Telefon hattı gibi):
  Tarayıcı ↔ Sunucu (bağlantı hep açık)
  Sunucu: "Hey, yeni issue oluşturuldu! İşte veri." (anlık ileti)
  Sunucu: "Hey, sprint başladı!" (anlık ileti)
  (Tek bağlantı, anlık iletişim)
```

---

## 3. Reverb Mimarisi: Büyük Resim

```
┌──────────────┐      ┌──────────────────┐      ┌──────────────────┐
│   PHP Event  │      │   Reverb Server  │      │  JavaScript      │
│   dispatch() │─────→│   (WebSocket)    │─────→│  Laravel Echo    │
│              │      │   Port 8080      │      │  (Tarayıcı)      │
└──────────────┘      └──────────────────┘      └──────────────────┘
                             │
                    Kanal Yetkilendirme
                    (routes/channels.php)
                             │
                    "Bu kullanıcı bu kanalı
                     dinleyebilir mi?"
```

### Akış Detayı

```
1. PHP'de bir şey olur (ör: IssueService::create())
2. Event dispatch edilir → IssueCreated::dispatch($issue, $user)
3. Event ShouldBroadcast interface'ini implemente eder
4. Laravel, event'i Reverb sunucusuna gönderir
5. Reverb, event'i ilgili kanala bağlı tüm istemcilere iletir
6. JavaScript (Echo), event'i alır ve UI'ı günceller
```

---

## 4. Yapılandırma Dosyaları

### 4.1 `config/broadcasting.php` — Broadcasting Bağlantısı

```php
return [
    // Hangi broadcasting driver kullanılacak?
    // .env dosyasında BROADCAST_CONNECTION=reverb olarak ayarlanır
    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            // Reverb sunucusuna bağlantı bilgileri (env'den gelir)
            'key' => env('REVERB_APP_KEY'),       // Uygulama anahtarı
            'secret' => env('REVERB_APP_SECRET'),  // Uygulama gizli anahtarı
            'app_id' => env('REVERB_APP_ID'),      // Uygulama ID'si
            'options' => [
                'host' => env('REVERB_HOST'),       // Sunucu adresi
                'port' => env('REVERB_PORT', 443),  // Port numarası
                'scheme' => env('REVERB_SCHEME', 'https'), // HTTP veya HTTPS
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https', // TLS kullan?
            ],
        ],
        // Geliştirme ortamında 'log' kullanılarak event'ler log dosyasına yazılabilir
        'log' => ['driver' => 'log'],
        // Test ortamında 'null' ile broadcast tamamen devre dışı bırakılır
        'null' => ['driver' => 'null'],
    ],
];
```

**Syntax Açıklamaları:**
- `env('REVERB_APP_KEY')`: `.env` dosyasından değer okur. Gizli bilgiler hiçbir zaman kod içine yazılmaz.
- `'driver' => 'reverb'`: Laravel'e "broadcasting için Reverb kullan" der.

### 4.2 `config/reverb.php` — Reverb Sunucu Ayarları

```php
return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            // Sunucu dinleme ayarları
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'), // Tüm IP'lerden bağlantı kabul et
            'port' => env('REVERB_SERVER_PORT', 8080),       // WebSocket port'u
            'hostname' => env('REVERB_HOST'),                 // Dış erişim hostname

            'options' => [
                'tls' => [], // TLS sertifika ayarları (production'da dolar)
            ],

            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000), // Max mesaj boyutu (byte)

            // Redis ile ölçekleme (birden fazla sunucu için)
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'),
                ],
            ],
        ],
    ],

    'apps' => [
        'provider' => 'config', // Uygulama bilgileri config'den gelir

        'apps' => [[
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
            ],
            'allowed_origins' => ['*'],                        // Hangi domain'lerden bağlantı kabul et
            'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),      // Bağlantı canlılık kontrolü (saniye)
            'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30), // İnaktif bağlantı zaman aşımı
            'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
        ]],
    ],
];
```

**Önemli Ayarlar:**
- `'host' => '0.0.0.0'`: Sunucu tüm ağ arayüzlerinden bağlantı kabul eder
- `'allowed_origins' => ['*']`: Herhangi bir domain'den WebSocket bağlantısı kabul eder (production'da kısıtlanmalı)
- `ping_interval`: Her 60 saniyede bağlantının hâlâ aktif olup olmadığını kontrol eder

### 4.3 `bootstrap/app.php` — Broadcasting Kaydı

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    // Broadcasting'i etkinleştir ve kanal dosyasını belirt
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',             // Kanal yetkilendirme dosyası
        ['middleware' => ['web', 'auth']],              // Kanal yetkilendirme middleware'leri
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ...
    })
    ->create();
```

**Syntax Açıklamaları:**
- `->withBroadcasting()`: Laravel'e "broadcasting kullanacağız" diyoruz
- İlk parametre: Kanal yetkilendirme kurallarının dosya yolu
- `['middleware' => ['web', 'auth']]`: Kanal yetkilendirmesi için session ve kimlik doğrulama middleware'lerini uygula

---

## 5. Broadcasting Altyapısı

### ShouldBroadcast Interface

Bir event'in WebSocket üzerinden yayınlanması için `ShouldBroadcast` interface'ini implement etmesi gerekir.

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class IssueCreated implements ShouldBroadcast
{
    // Bu event, queue üzerinden asenkron yayınlanır
}
```

**`ShouldBroadcast` vs `ShouldBroadcastNow`:**
- `ShouldBroadcast`: Event queue'ya eklenir, arka planda yayınlanır (önerilen)
- `ShouldBroadcastNow`: Event hemen yayınlanır, istek yanıtını bekletir

Bu projede tüm event'ler **`ShouldBroadcast`** kullanır — yani asenkron çalışır, kullanıcıyı bekletmez.

### Broadcast Event'inin 3 Zorunlu Bileşeni

Her broadcast event'inde mutlaka bu 3 metot bulunur:

```php
class IssueCreated implements ShouldBroadcast
{
    // 1. broadcastOn() → Event HANGİ KANALA gidecek?
    public function broadcastOn(): array { ... }

    // 2. broadcastAs() → Event'in ADI ne olacak? (JS tarafında dinlerken kullanılır)
    public function broadcastAs(): string { ... }

    // 3. broadcastWith() → Event ile HANGİ VERİ gidecek?
    public function broadcastWith(): array { ... }
}
```

---

## 6. Kanal (Channel) Sistemi

### Kanal Türleri

| Tür | Sınıf | Açıklama | Yetkilendirme |
|-----|-------|----------|---------------|
| Public | `Channel` | Herkes dinleyebilir | Yok |
| **Private** | `PrivateChannel` | **Sadece yetkili kullanıcılar** | Gerekli ✅ |
| Presence | `PresenceChannel` | Özel + kim online (çevrimiçi) bilgisi | Gerekli |

> Bu projede **yalnızca `PrivateChannel`** kullanılır — çünkü proje verileri gizlidir.

### `routes/channels.php` — Kanal Yetkilendirme Kuralları

```php
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// ──────────────────────────────────────────────
// Kanal 1: Proje Kanalı
// ──────────────────────────────────────────────
// Kullanım: PrivateChannel('project.{projectId}')
// Kim dinleyebilir? → Projenin üyesi olan kullanıcılar
Broadcast::channel('project.{projectId}', function (User $user, string $projectId) {
    return $user->projectMemberships()
        ->where('project_id', $projectId)
        ->exists();
});

// ──────────────────────────────────────────────
// Kanal 2: Kullanıcı Kanalı
// ──────────────────────────────────────────────
// Kullanım: PrivateChannel('user.{userId}')
// Kim dinleyebilir? → Sadece o kullanıcının kendisi
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

// ──────────────────────────────────────────────
// Kanal 3: Laravel Varsayılan Kullanıcı Kanalı
// ──────────────────────────────────────────────
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

**Syntax Açıklamaları:**

- **`Broadcast::channel('project.{projectId}', function ...)`:**
  - İlk parametre: Kanal adı pattern'i. `{projectId}` dinamik bir parametredir — gerçek UUID ile değiştirilir.
  - İkinci parametre: Yetkilendirme callback'i. `true` dönerse kullanıcı bu kanalı dinleyebilir.
  - `$user`: Laravel otomatik olarak kimliği doğrulanmış kullanıcıyı geçer.
  - `$projectId`: URL'deki dinamik parametre.

- **Nasıl çalışır?**
  1. Tarayıcı (Echo) `private-project.abc123` kanalına abone olmak ister
  2. Echo, `/broadcasting/auth` endpoint'ine POST isteği gönderir
  3. Laravel, `channels.php`'deki callback'i çalıştırır
  4. Callback `true` dönerse → abone olur, `false` → reddedilir

---

## 7. Event Sınıfları ve Broadcast Syntax'ı

Bir broadcast event'inin anatomisini detaylıca inceleyelim:

### Tam Bir Event Sınıfı (Satır Satır Açıklama)

```php
<?php

declare(strict_types=1);

namespace App\Events\Issue;

// ─── Import'lar ───

use App\Models\Issue;
use App\Models\User;

// InteractsWithSockets: WebSocket bağlantı bilgisine erişim sağlar
use Illuminate\Broadcasting\InteractsWithSockets;

// PrivateChannel: Yetkilendirmeli kanal sınıfı
use Illuminate\Broadcasting\PrivateChannel;

// ShouldBroadcast: "Bu event WebSocket'ten yayınlanacak" der
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

// Dispatchable: Event::dispatch() ile tetiklenebilmeyi sağlar
use Illuminate\Foundation\Events\Dispatchable;

// SerializesModels: Event queue'ya girdiğinde modelleri doğru serialize eder
use Illuminate\Queue\SerializesModels;


class IssueCreated implements ShouldBroadcast
{
    // ─── Trait'ler ───
    use Dispatchable;          // → IssueCreated::dispatch($issue, $user) kullanımını sağlar
    use InteractsWithSockets;  // → WebSocket bağlantı yönetimi
    use SerializesModels;      // → Queue'da model ID'leri saklanır, deserialize'da DB'den çekilir

    // ─── Constructor ───
    // readonly: Oluşturulduktan sonra değiştirilemez (immutability)
    // public: Listener'lar doğrudan erişebilir ($event->issue, $event->creator)
    public function __construct(
        public readonly Issue $issue,
        public readonly User $creator,
    ) {}

    // ─── 1. KANALI BELİRLE ───
    // Bu event hangi WebSocket kanalına gönderilecek?
    /** @return array<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            // Projenin özel kanalı — sadece proje üyeleri alır
            new PrivateChannel('project.'.$this->issue->project_id),
            // Birden fazla kanal dönebilirsin (ör: ek olarak user kanalı)
        ];
    }

    // ─── 2. EVENT ADINI BELİRLE ───
    // JavaScript tarafında bu isimle dinlenecek:
    // Echo.private('project.xxx').listen('.issue.created', callback)
    // Not: başına nokta (.) konur çünkü broadcastAs kullanıyoruz
    public function broadcastAs(): string
    {
        return 'issue.created';
    }

    // ─── 3. GÖNDERİLECEK VERİYİ BELİRLE ───
    // Sadece ihtiyaç duyulan alanlar gönderilir (tüm model değil!)
    // Güvenlik: Hassas veri (password, token) asla gönderilmez
    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'issue_id' => $this->issue->id,
            'title' => $this->issue->title,
            'created_by' => $this->creator->id,
        ];
    }
}
```

### Trait'lerin Görevleri

| Trait | Ne Yapar | Neden Gerekli |
|-------|----------|---------------|
| `Dispatchable` | `Event::dispatch()` static metodu sağlar | Event'i kolay tetiklemek için |
| `InteractsWithSockets` | WebSocket bağlantı bilgisi yönetimi, `dontBroadcastToCurrentUser()` | Tetikleyen kullanıcıya tekrar gönderilmesin diye |
| `SerializesModels` | Queue'da Eloquent model ID'lerini saklar | Event queue'ya girdiğinde model taze kalır |

---

## 8. Tüm Broadcast Event'leri (Detaylı)

### 8.1 `IssueCreated` — Yeni Issue Oluşturuldu

```php
// Dosya: app/Events/Issue/IssueCreated.php
// Tetikleyen: IssueService::create()
// Kanal: project.{projectId}
// Event Adı: issue.created

public function broadcastOn(): array
{
    return [new PrivateChannel('project.'.$this->issue->project_id)];
}

public function broadcastAs(): string
{
    return 'issue.created';
}

public function broadcastWith(): array
{
    return [
        'issue_id' => $this->issue->id,
        'title' => $this->issue->title,
        'created_by' => $this->creator->id,
    ];
}
```

**Kullanım Senaryosu:** Bir takım üyesi yeni bug raporu oluşturduğunda, issue listesini açık tutan diğer üyeler listeyi yenilemeden yeni issue'yu görür.

---

### 8.2 `IssueStatusChanged` — Issue Durumu Değişti

```php
// Dosya: app/Events/Issue/IssueStatusChanged.php
// Tetikleyen: IssueService::changeStatus()
// Kanal: project.{projectId}
// Event Adı: issue.status-changed

public function __construct(
    public readonly Issue $issue,
    public readonly string $oldStatus,    // Önceki durum (ör: "new")
    public readonly string $newStatus,    // Yeni durum (ör: "in_progress")
    public readonly User $changedBy,
) {}

public function broadcastWith(): array
{
    return [
        'issue_id' => $this->issue->id,
        'old_status' => $this->oldStatus,
        'new_status' => $this->newStatus,
        'changed_by' => $this->changedBy->id,
    ];
}
```

**Kullanım Senaryosu:** Kanban board'da bir issue sürüklenip farklı bir sütuna bırakıldığında, diğer kullanıcılar değişikliği anında görür.

---

### 8.3 `StoryCreated` — Yeni User Story Oluşturuldu

```php
// Dosya: app/Events/Scrum/StoryCreated.php
// Tetikleyen: UserStoryService::create()
// Kanal: project.{projectId}
// Event Adı: story.created

public function broadcastWith(): array
{
    return [
        'story_id' => $this->story->id,
        'story_title' => $this->story->title,
        'created_by' => $this->creator->id,
    ];
}
```

**Kullanım Senaryosu:** Backlog sayfasında yeni bir hikaye oluşturulduğunda, diğer üyeler anında görür.

---

### 8.4 `StoryStatusChanged` — Story Durumu Değişti

```php
// Dosya: app/Events/Scrum/StoryStatusChanged.php
// Tetikleyen: UserStoryService::changeStatus()
// Kanal: project.{projectId}
// Event Adı: story.status-changed

public function broadcastWith(): array
{
    return [
        'story_id' => $this->story->id,
        'old_status' => $this->oldStatus,
        'new_status' => $this->newStatus,
        'changed_by' => $this->changedBy->id,
    ];
}
```

**Kullanım Senaryosu:** Sprint board'da bir story "Done" yapıldığında, diğer üyeler anında görür. Aynı zamanda burndown chart güncellenir.

---

### 8.5 `TaskStatusChanged` — Task Durumu Değişti

```php
// Dosya: app/Events/Scrum/TaskStatusChanged.php
// Tetikleyen: TaskService::changeStatus()
// Kanal: project.{projectId}
// Event Adı: task.status-changed

// Dikkat: Task'ın project_id'si YOK — UserStory üzerinden erişilir
public function broadcastOn(): array
{
    return [
        new PrivateChannel('project.'.$this->task->userStory->project_id),
    ];
}

public function broadcastWith(): array
{
    return [
        'task_id' => $this->task->id,
        'story_id' => $this->task->user_story_id,  // Hangi story'nin task'ı
        'old_status' => $this->oldStatus,
        'new_status' => $this->newStatus,
        'changed_by' => $this->changedBy->id,
    ];
}
```

**Dikkat:** Task modeli doğrudan bir `project_id`'ye sahip değil. Bu yüzden `$this->task->userStory->project_id` kullanılarak dolaylı yol ile proje kanalına gönderilir.

---

### 8.6 `TaskAssigned` — Görev Birine Atandı

```php
// Dosya: app/Events/Scrum/TaskAssigned.php
// Tetikleyen: TaskService::assign()
// Kanal: user.{userId} (proje değil, KİŞİSEL kanal!)
// Event Adı: task.assigned

public function broadcastOn(): array
{
    return [
        // Sadece atanan kişiye gönder (kişisel bildirim)
        new PrivateChannel("user.{$this->assignee->id}"),
    ];
}

public function broadcastWith(): array
{
    return [
        'task_id' => $this->task->id,
        'task_title' => $this->task->title,
        'assignee_id' => $this->assignee->id,
        'assigned_by' => $this->assignedBy->id,
    ];
}
```

**Fark:** Bu event `project.*` kanalına değil, `user.*` kanalına gider. Çünkü bu kişisel bir bildirim — sadece atanan kişi görmelidir.

---

### 8.7 `SprintStarted` — Sprint Başlatıldı

```php
// Dosya: app/Events/Scrum/SprintStarted.php
// Tetikleyen: SprintService::start()
// Kanal: project.{projectId}
// Event Adı: sprint.started

public function broadcastWith(): array
{
    return [
        'sprint_id' => $this->sprint->id,
        'sprint_name' => $this->sprint->name,
        'started_by' => $this->startedBy->id,
    ];
}
```

**Kullanım Senaryosu:** Sprint başlatıldığında tüm takım üyelerine anlık bildirim gider. Kanban board aktif hale gelir.

---

### 8.8 `SprintClosed` — Sprint Kapatıldı

```php
// Dosya: app/Events/Scrum/SprintClosed.php
// Tetikleyen: SprintService::close()
// Kanal: project.{projectId}
// Event Adı: sprint.closed

public function broadcastWith(): array
{
    return [
        'sprint_id' => $this->sprint->id,
        'sprint_name' => $this->sprint->name,
        'closed_by' => $this->closedBy->id,
    ];
}
```

**Kullanım Senaryosu:** Sprint kapatılır, bitmemiş hikayeler backlog'a döner, tüm takım UI güncellemesi alır.

---

### 8.9 `MemberAdded` — Projeye Üye Eklendi

```php
// Dosya: app/Events/Project/MemberAdded.php
// Tetikleyen: MembershipService::add()
// Kanal: IKISIDE! user.{userId} + project.{projectId}
// Event Adı: member.added

public function broadcastOn(): array
{
    return [
        // 1. Yeni üyeye kişisel bildirim
        new PrivateChannel("user.{$this->member->id}"),
        // 2. Proje üyelerine güncelleme
        new PrivateChannel("project.{$this->project->id}"),
    ];
}

public function broadcastWith(): array
{
    return [
        'project_id' => $this->project->id,
        'project_name' => $this->project->name,
        'member_id' => $this->member->id,
        'role' => $this->membership->role->value,
        'added_by' => $this->addedBy->id,
    ];
}
```

**Dikkat:** Bu event **2 farklı kanala** aynı anda gönderilir:
1. Yeni eklenen kullanıcıya: "Bir projeye eklendin!"
2. Mevcut proje üyelerine: "Yeni bir üye katıldı!"

---

### 8.10 `NotificationSent` — Bildirim Gönderildi

```php
// Dosya: app/Events/Notification/NotificationSent.php
// Tetikleyen: SendNotificationAction::execute()
// Kanal: user.{userId}
// Event Adı: notification.received

public function broadcastOn(): array
{
    return [
        new PrivateChannel("user.{$this->userId}"),
    ];
}

public function broadcastWith(): array
{
    return [
        'id' => $this->notification->id,
        'type' => $this->notification->type,
        'data' => $this->notification->data,
    ];
}
```

**Kullanım Senaryosu:** Herhangi bir bildirim gönderildiğinde (task atama, durum değişikliği, üye ekleme), kullanıcının bildirim çanı anında güncellenir.

---

### 8.11 Broadcast OLMAYAN Event'ler

Bu event'ler **yalnızca sunucu tarafında** Listener'lar tarafından işlenir:

| Event | Neden Broadcast Yok |
|-------|---------------------|
| `ProjectCreated` | Proje yeni oluşturuldu, henüz dinleyecek kimse yok |
| `MemberRemoved` | Çıkarılan kişi zaten kanaldan kopuyor |

```php
// MemberRemoved — ShouldBroadcast YOK
class MemberRemoved
{
    use Dispatchable, SerializesModels;
    // InteractsWithSockets bile yok — broadcast ihtiyacı yok
}
```

---

## 9. Service Katmanında Event Dispatch

### Dispatch Pattern (Tüm Servisler)

```php
// Genel kalıp:
public function someMethod(/* params */): Model
{
    // 1. Veritabanı işlemi (transaction içinde)
    $model = DB::transaction(function () {
        return $this->action->execute(/* params */);
    });

    // 2. Broadcast event dispatch (transaction dışında!)
    try {
        SomeEvent::dispatch($model, $user);
    } catch (BroadcastException $e) {
        Log::warning('Broadcast failed for SomeEvent', ['error' => $e->getMessage()]);
    }

    return $model;
}
```

**Neden bu sırayla?**

1. **Transaction içinde Action:** Veritabanı değişikliği başarılı olmalı
2. **Transaction dışında Event:** Eğer transaction rollback olursa event gönderilmesin
3. **try-catch:** Broadcast hatası uygulama hatası sayılmaz. Reverb sunucu çökmüş olabilir ama issue yine de oluşturulmuş olmalı.

### Tüm Service → Event Eşlemeleri

| Service | Metot | Dispatch Edilen Event |
|---------|-------|----------------------|
| `IssueService` | `create()` | `IssueCreated` |
| `IssueService` | `changeStatus()` | `IssueStatusChanged` |
| `UserStoryService` | `create()` | `StoryCreated` |
| `UserStoryService` | `changeStatus()` | `StoryStatusChanged` |
| `TaskService` | `changeStatus()` | `TaskStatusChanged` |
| `TaskService` | `assign()` | `TaskAssigned` |
| `SprintService` | `start()` | `SprintStarted` |
| `SprintService` | `close()` | `SprintClosed` |
| `MembershipService` | `add()` | `MemberAdded` |
| `MembershipService` | `remove()` | `MemberRemoved` (broadcast yok) |
| `ProjectService` | `create()` | `ProjectCreated` (broadcast yok) |
| `NotificationService` | (via Action) | `NotificationSent` |

---

## 10. Frontend: Laravel Echo Entegrasyonu

### 10.1 `resources/js/echo.js` — Echo Yapılandırması

```javascript
// Laravel Echo: WebSocket istemci kütüphanesi
import Echo from 'laravel-echo';

// Pusher-js: Reverb ile uyumlu WebSocket protokolü
import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Echo instance oluştur
window.Echo = new Echo({
    // ─── broadcaster ───
    // 'reverb' kullan (Pusher protokolüyle uyumlu)
    broadcaster: 'reverb',

    // ─── key ───
    // Vite build sırasında .env'den enjekte edilir
    // VITE_ prefix'li env değişkenleri frontend'de kullanılabilir
    key: import.meta.env.VITE_REVERB_APP_KEY,

    // ─── WebSocket Host ───
    // Reverb sunucusunun adresi
    wsHost: import.meta.env.VITE_REVERB_HOST,

    // ─── Port Ayarları ───
    // ws (güvenli olmayan) port ve wss (güvenli) port
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,

    // ─── TLS (SSL) Ayarı ───
    // HTTPS kullanılıyorsa TLS zorunlu
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',

    // ─── Transport Protokolü ───
    // HTTPS → wss (güvenli WebSocket), HTTP → ws
    enabledTransports: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https'
        ? ['wss']    // Güvenli WebSocket
        : ['ws'],     // Normal WebSocket
});
```

**Syntax Açıklamaları:**

| Ayar | Açıklama |
|------|----------|
| `broadcaster: 'reverb'` | Echo'ya Reverb protokolü kullanmasını söyler |
| `import.meta.env.VITE_*` | Vite build aracı `.env`'deki `VITE_` prefix'li değişkenleri JS'e enjekte eder |
| `forceTLS: true` | WebSocket bağlantısında TLS şifreleme kullanılır |
| `enabledTransports: ['wss']` | Yalnızca güvenli WebSocket protokolü aktif (production) |
| `window.Echo` | Global erişim için window nesnesine eklenir |
| `window.Pusher = Pusher` | Echo, Pusher-js kütüphanesini alt yapıda kullanır |

### 10.2 `resources/js/bootstrap.js` — Yükleme Zinciri

```javascript
import axios from 'axios';
window.axios = axios;

// CSRF koruması için header ekle
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Echo ve Reverb bağlantısını başlat
import './echo';
```

### 10.3 Frontend'de Event Dinleme (Kullanım Örneği)

Echo kurulduktan sonra, JavaScript tarafında event'ler şöyle dinlenir:

```javascript
// ─── Proje kanalını dinle ───
// private() → PrivateChannel
// 'project.abc123' → Kanal adı (project_id ile)
Echo.private('project.abc123')

    // .listen() → Event dinle
    // '.issue.created' → broadcastAs() ile belirlenen event adı
    // Başındaki nokta (.) önemli! broadcastAs() kullanıldığında gereklidir
    .listen('.issue.created', (data) => {
        // data = broadcastWith() ile gönderilen veri
        console.log('Yeni issue:', data.title);
        console.log('Oluşturan:', data.created_by);
        // UI'ı güncelle...
    })

    .listen('.sprint.started', (data) => {
        console.log('Sprint başladı:', data.sprint_name);
        // Board'u güncelle...
    })

    .listen('.story.status-changed', (data) => {
        console.log(`Story ${data.story_id}: ${data.old_status} → ${data.new_status}`);
        // Kanban kartını taşı...
    });

// ─── Kişisel kanalı dinle ───
Echo.private('user.my-user-id')

    .listen('.notification.received', (data) => {
        console.log('Yeni bildirim:', data.type);
        // Bildirim çanını güncelle...
        showToast(data.data.message);
    })

    .listen('.task.assigned', (data) => {
        console.log('Size yeni görev atandı:', data.task_title);
    });
```

**Önemli:** `broadcastAs()` metodu kullanıldığında, JS tarafında event adının başına **nokta (.)** eklenir:
- PHP: `return 'issue.created';`
- JS: `.listen('.issue.created', ...)`

Nokta koymayı unutursanız, Echo event'i namespace'li (`App\\Events\\Issue\\IssueCreated`) adıyla arar ve eşleşmez!

---

## 11. Listener'lar ve Broadcasting Zinciri

Bazı event'ler hem broadcast edilir hem de Listener'lar tarafından işlenir. Bu, 2 paralel işlemdir:

### StoryStatusChanged Zinciri

```
UserStoryService::changeStatus()
      │
      ├──→ StoryStatusChanged::dispatch() ──── ShouldBroadcast
      │         │                                    │
      │         │                                    ▼
      │         │                           WebSocket → project.{id}
      │         │                           JS → '.story.status-changed'
      │         │
      │         ├──→ RecalculateEpicCompletion (Listener)
      │         │         → Epic tamamlanma yüzdesi güncellenir
      │         │
      │         ├──→ SendStatusChangeNotification (Listener)
      │         │         → DB'ye notification kaydet
      │         │         → NotificationSent::dispatch()
      │         │              → WebSocket → user.{id}
      │         │              → JS → '.notification.received'
      │         │
      │         └──→ UpdateBurndownSnapshot (Listener)
      │                   → Burndown cache güncelle
```

### Bildirim Listener'larının Broadcasting İlişkisi

Listener'lar `SendNotificationAction`'ı çağırır ve bu action da kendi event'ini dispatch eder:

```php
// SendNotificationAction::execute()
class SendNotificationAction
{
    public function execute(User $user, string $type, array $data): void
    {
        // 1. Bildirimi veritabanına kaydet
        $notification = $user->notifications()->create([
            'type' => $type,
            'data' => $data,
        ]);

        // 2. NotificationSent event'ini broadcast et
        try {
            NotificationSent::dispatch($notification, $user->id);
        } catch (BroadcastException $e) {
            Log::warning('Broadcast failed for NotificationSent', ['error' => $e->getMessage()]);
        }
    }
}
```

Bu sayede her bildirim hem veritabanına kaydedilir hem de WebSocket üzerinden anlık gönderilir.

---

## 12. Hata Yönetimi

### BroadcastException Yakalama

Tüm Service'lerde broadcast hataları güvenli bir şekilde yakalanır:

```php
try {
    IssueCreated::dispatch($issue, $user);
} catch (BroadcastException $e) {
    // Broadcast hatası loglara yazılır ama istek başarılı döner
    Log::warning('Broadcast failed for IssueCreated', ['error' => $e->getMessage()]);
}
```

**Neden bu yaklaşım?**
- Reverb sunucusu çökmüş olabilir → Issue yine de oluşturulmuş olmalı
- Ağ gecikmesi olabilir → Kullanıcı beklemesin
- Broadcasting bir **yan etki**tir, **ana işlev** değil

### Olası Hata Senaryoları

| Senaryo | Ne Olur |
|---------|---------|
| Reverb sunucusu kapalı | Event dispatch edilir ama broadcast olmaz. Log'a uyarı yazılır. |
| Kullanıcı bağlantısı kopuk | Event Reverb'e ulaşır ama istemciye iletilemez. Kullanıcı sayfayı yenilediğinde veriyi görür. |
| Queue çalışmıyor | ShouldBroadcast event'leri queue'da birikir, queue başlayınca gönderilir. |

---

## 13. Veri Akış Diyagramları

### Tam Broadcast Akışı (Issue Statusu Değiştiğinde)

```
Kullanıcı (Tarayıcı)
      │
      │ PUT /api/projects/my-project/issues/uuid/status
      │ { "status": "in_progress" }
      │
      ▼
┌─── IssueController::changeStatus() ───┐
│   $this->authorize('changeStatus')     │
│   $issueService->changeStatus(...)     │
└───────────────┬────────────────────────┘
                │
                ▼
┌─── IssueService::changeStatus() ──────┐
│   DB::transaction {                    │
│     ChangeIssueStatusAction::execute() │
│     → State machine geçiş kontrolü    │
│     → issue.status = 'in_progress'    │
│   }                                    │
│                                        │
│   IssueStatusChanged::dispatch(        │
│     $issue, 'new', 'in_progress', $user│
│   )                                    │
└───────────────┬────────────────────────┘
                │
    ┌───────────┴───────────┐
    │                       │
    ▼                       ▼
[Queue Worker]         [Listener'lar]
    │                       │
    ▼                       ▼
Reverb WebSocket    SendStatusChange
Server                Notification
    │                       │
    ▼                       ▼
┌────────────┐    ┌────────────────┐
│ project.   │    │ Notification   │
│ {projectId}│    │ DB'ye kaydet   │
│ kanalı     │    └───────┬────────┘
└─────┬──────┘            │
      │              NotificationSent
      │              ::dispatch()
      │                    │
      ▼                    ▼
Echo.private(       Echo.private(
  'project.xx'        'user.yy'
)                   )
.listen(            .listen(
  '.issue.status-     '.notification.
   changed'            received'
)                   )
      │                    │
      ▼                    ▼
UI Güncelle:        Bildirim Çanı:
Kanban kartı        🔔 "1 yeni
taşındı              bildirim"
```

### Kanal Bazlı Event Matrisi

```
project.{projectId} kanalı:
──────────────────────────────
  .issue.created          ←── IssueService::create()
  .issue.status-changed   ←── IssueService::changeStatus()
  .story.created          ←── UserStoryService::create()
  .story.status-changed   ←── UserStoryService::changeStatus()
  .task.status-changed    ←── TaskService::changeStatus()
  .sprint.started         ←── SprintService::start()
  .sprint.closed          ←── SprintService::close()
  .member.added           ←── MembershipService::add()

user.{userId} kanalı:
──────────────────────────────
  .task.assigned           ←── TaskService::assign()
  .notification.received   ←── SendNotificationAction::execute()
  .member.added            ←── MembershipService::add()
```

---

## 14. Özet: Nerede, Ne, Nasıl?

### Dosya Bazlı Referans

| Dosya | İçerik | Amaç |
|-------|--------|------|
| `config/broadcasting.php` | Broadcasting driver ayarları | Laravel'e Reverb'ü kullanmasını söyler |
| `config/reverb.php` | Reverb sunucu ayarları | WebSocket sunucu host, port, TLS |
| `bootstrap/app.php` | `withBroadcasting()` çağrısı | Broadcasting'i aktifleştirir, kanalları yükler |
| `routes/channels.php` | `Broadcast::channel()` tanımları | Kanal yetkilendirme kuralları |
| `app/Events/Issue/*.php` | Issue broadcast event'leri | Issue CRUD broadcast verileri |
| `app/Events/Scrum/*.php` | Scrum broadcast event'leri | Sprint, Story, Task broadcast verileri |
| `app/Events/Project/*.php` | Project event'leri | Üye ekleme broadcast verisi |
| `app/Events/Notification/*.php` | Bildirim event'i | Kişisel bildirim broadcast verisi |
| `app/Services/*.php` | Event dispatch çağrıları | Broadcasting'i tetikleyen iş akışı |
| `app/Actions/Notification/SendNotificationAction.php` | NotificationSent dispatch | Bildirim yayını |
| `resources/js/echo.js` | Echo yapılandırması | Frontend WebSocket bağlantısı |
| `resources/js/bootstrap.js` | Echo import | Echo'yu uygulama başlangıcında yükler |

### Syntax Hızlı Referans

```php
// ─── PHP Tarafı ───

// 1. Event tanımla (broadcast olacak)
class MyEvent implements ShouldBroadcast { ... }

// 2. Kanalı belirle
new PrivateChannel('project.'.$this->model->project_id)

// 3. Event adını belirle
public function broadcastAs(): string { return 'my.event-name'; }

// 4. Veriyi belirle
public function broadcastWith(): array { return ['key' => 'value']; }

// 5. Event'i tetikle
MyEvent::dispatch($model, $user);

// 6. Kanalı yetkilendir
Broadcast::channel('project.{id}', function(User $user, string $id) {
    return $user->isMemberOf(Project::find($id));
});

// 7. Broadcasting'i aktifleştir
->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['web', 'auth']])
```

```javascript
// ─── JavaScript Tarafı ───

// 1. Echo'yu yapılandır
window.Echo = new Echo({ broadcaster: 'reverb', key: '...', ... });

// 2. Kanala abone ol
Echo.private('project.abc123')

// 3. Event dinle (başına nokta!)
    .listen('.my.event-name', (data) => {
        console.log(data.key); // 'value'
    });
```

### Reverb'ü Çalıştırma

```bash
# Reverb WebSocket sunucusunu başlat
php artisan reverb:start

# Queue worker başlat (ShouldBroadcast event'leri queue kullanır)
php artisan queue:work

# Geliştirme modunda her ikisini birden başlat
composer run dev
```
