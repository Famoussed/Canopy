# 01 — Architecture Overview

Sistemin genel mimari yapısı, teknoloji yığını ve tasarım kararlarının gerekçeleri.

**İlişkili Dokümanlar:** [Project Structure](./08-PROJECT_STRUCTURE.md) | [Coding Standards](./14-CODING_STANDARDS.md)

---

## 1. Mimari Tanım

> **Service + Action Layered Architecture with Clean Architecture Principles**
>
> Clean Architecture'ın prensiplerini (katman ayrımı, tek yönlü bağımlılık, iş mantığı izolasyonu)
> benimseyen, ancak Laravel'in pragmatik yapısını (Eloquent, FormRequest) framework'e karşı değil
> framework ile çalışarak uygulayan katmanlı mimari.

### Neden "Lightweight Clean Architecture"?

| Uygulanan Prensip | Açıklama |
|---|---|
| Katman ayrımı | Controller → Service → Action → Model. Atlanmaz. |
| Tek yönlü bağımlılık | İç katman (Model) dış katmanı (Controller) asla bilmez |
| İş mantığı izolasyonu | Service/Action HTTP'den bağımsız, direkt test edilebilir |
| Tek sorumluluk | Her katmanın net bir görevi var |

| Strict Clean Arch'tan Bilinçli Sapma | Gerekçe |
|---|---|
| Repository interface yok | DB değişimi planlanmıyor, 28 ekstra dosya = gereksiz overhead |
| Domain Entity saf PHP değil | Eloquent relationship, scope, cast özelliklerini kaybetmek mantıksız |
| DTO katmanı yok | `$request->validated()` array olarak yeterli, bu ölçekte type-safe DTO overkill |

### Neden Namespace-Based (Modüler Değil)?

- Modüller arası bağımlılık çok yüksek (Analytics → Scrum → Project, Notification → hepsi)
- Tek geliştirici / küçük ekip — modül izolasyonu gereksiz overhead
- Monolith deployment — bağımsız deploy ihtiyacı yok
- Migration sıralaması, cross-module FK, shared model erişimi problemlerinden kaçınılıyor
- Laravel'in doğal yapısıyla uyumlu

---

## 2. Katman Mimarisi

```
┌──────────────────────────────────────────────────────────────┐
│  HTTP Request / Livewire Action                              │
├──────────────────────────────────────────────────────────────┤
│  FormRequest          │  Validation + Authorization          │
├──────────────────────────────────────────────────────────────┤
│  Controller /         │  İNCE — sadece routing               │
│  Livewire Component   │  Service çağırır, iş mantığı YASAK   │
├──────────────────────────────────────────────────────────────┤
│  Service              │  BEYİN — iş mantığı orkestrasyon     │
│                       │  DB::transaction, Event dispatch     │
├──────────────────────────────────────────────────────────────┤
│  Action               │  İŞÇİ — tek amaçlı operasyonlar      │
│                       │  Yeniden kullanılabilir              │
├──────────────────────────────────────────────────────────────┤
│  Model                │  VERİ — Eloquent, Query Scopes       │
│                       │  Relationship, Cast, Accessor        │
├──────────────────────────────────────────────────────────────┤
│  Domain Events → Listeners                                   │
│  (Bildirim, Analytics, Activity Log)                         │
└──────────────────────────────────────────────────────────────┘
```

### Request Akışı

```
HTTP Request / Livewire Action
        ↓
FormRequest (validation + authorize)
        ↓
Controller / Livewire Component
        ↓
Service::method()
        ↓
  ┌─── DB::transaction ───┐
  │  Action::execute()     │
  │  Action::execute()     │
  │  Event::dispatch()     │
  └────────────────────────┘
        ↓
Response / Livewire re-render
```

### Katman İletişim Kuralları

| Katman | Amaç | Çağırabilir | Çağıramaz |
|--------|-------|-------------|-----------|
| Controller | HTTP handling | **Sadece Service** | Model, Action, DB |
| Livewire Component | UI state + Service çağrısı | **Sadece Service** | Model, Action, DB |
| Service | İş mantığı orkestrasyon | Action, Model, Event dispatch | Controller, Livewire |
| Action | Tek amaçlı operasyon | Model, diğer Action'lar | Service, Controller |
| FormRequest | Validation | — | — |
| Policy | Authorization kararı | Model (sorgu) | — |
| Resource | API response dönüşümü | — | — |

**Katı Kural:** Katman atlama **YASAK**. Controller → Service → Action sırası her zaman korunur.

---

## 3. Teknoloji Yığını

| Katman | Teknoloji | Versiyon | Gerekçe |
|--------|-----------|----------|---------|
| Backend Framework | Laravel | 11.x | PHP ekosisteminin en olgun framework'ü |
| Frontend | Livewire | 3.x | SPA deneyimi, ayrı frontend gerekmez |
| Veritabanı (Dev) | SQLite | — | Hızlı geliştirme, sıfır konfigürasyon |
| Veritabanı (Prod) | PostgreSQL | 16 | JSONB, concurrent writes, LISTEN/NOTIFY, FTS |
| Cache / Queue / Session | Redis | 7 | Performans, broadcast, queue backend |
| Real-time WebSocket | Laravel Reverb | — | Native, ücretsiz, Livewire + Echo entegrasyonu |
| Dosya Depolama | MinIO | — | Self-hosted S3 klonu, Docker Compose uyumlu |
| Authentication | Session-based + Sanctum SPA | — | Livewire doğal uyum, cookie-based API |
| Deployment | Docker Compose | — | VPS/Dedicated, tek komutla ayağa kalkma |
| PHP | PHP | 8.3 | Enum, Fiber, typed properties |

### Teknoloji Karar Gerekçeleri

**Laravel + Livewire (Monolith):**
- Ayrı frontend repo/deploy gerektirmiyor
- Livewire 3 SPA-benzeri deneyim sunuyor
- Service katmanı hem web hem API'den çağrılabiliyor
- Tek codebase = daha kolay TDD

**SQLite (Dev) → PostgreSQL (Prod):**
- Laravel DB abstraction ile geçiş tek config değişikliği
- Dev'de sıfır dependency, hızlı `php artisan test`
- Prod'da JSONB (custom fields), concurrent writes (10K kullanıcı), LISTEN/NOTIFY (real-time)

**Session-based Auth:**
- `projeKuralları` zorunluluğu
- Livewire ile doğal uyum (cookie-based)
- API endpoint'ler Sanctum SPA mode (cookie auth, token değil)

**MinIO (Dosya Depolama):**
- S3-uyumlu API → Laravel'in S3 driver'ı direkt çalışır
- Docker Compose'a bir container eklemek yeterli
- Yoğun trafikte horizontal scale yapılabilir
- Geliştirme maliyeti minimum (ekstra paket gereksiz)

---

## 4. Mimari Diyagram (Bileşen İlişkileri)

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT (Browser)                         │
│                    Livewire + Laravel Echo                      │
└──────────┬──────────────────────────────────┬───────────────────┘
           │ HTTP / Livewire                  │ WebSocket
           ▼                                  ▼
┌─────────────────────┐            ┌─────────────────────┐
│       Nginx         │            │   Laravel Reverb    │
│   (Reverse Proxy)   │            │  (WebSocket Server) │
└──────────┬──────────┘            └──────────┬──────────┘
           │                                  │
           ▼                                  │
                      Laravel Application     │
┌─────────────────────────────────────────────┴───────────────────┐
│  ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌──────────────┐  │
│  │ Controller│  │ Livewire  │  │  Policy   │  │  FormRequest │  │
│  └─────┬─────┘  └─────┬─────┘  └───────────┘  └──────────────┘  │
│        │              │                                         │
│        └──────┬───────┘                                         │
│               ▼                                                 │
│  ┌────────────────────────┐                                     │
│  │       Services         │◄──── İş mantığı orkestrasyon        │
│  └────────────┬───────────┘                                     │
│               ▼                                                 │
│  ┌────────────────────────┐                                     │
│  │       Actions          │◄──── Tek amaçlı operasyonlar        │
│  └────────────┬───────────┘                                     │
│               ▼                                                 │
│  ┌────────────────────────┐     ┌──────────────┐                │
│  │    Eloquent Models     │────►│ Domain Events│                │
│  └────────────┬───────────┘     └──────┬───────┘                │
│               │                        │                        │
│               │                        ▼                        │
│               │                 ┌──────────────┐                │
│               │                 │  Listeners   │                │
│               │                 │ (Notify,     │                │
│               │                 │  Analytics,  │                │
│               │                 │  ActivityLog)│                │
│               │                 └──────────────┘                │
└───────────────┼─────────────────────────────────────────────────┘
                │
    ┌───────────┼───────────┬──────────────┐
    ▼           ▼           ▼              ▼
┌────────┐ ┌────────┐ ┌────────┐   ┌──────────┐
│PostgreSQL│ │ Redis  │ │ MinIO  │   │  Queue   │
│  (DB)   │ │(Cache/ │ │(Files) │   │ (Worker) │
│         │ │Session)│ │        │   │          │
└─────────┘ └────────┘ └────────┘   └──────────┘
```

---

## 5. Cross-Cutting Concerns

| Concern | Çözüm | Detay |
|---------|-------|-------|
| Authentication | Session + Sanctum SPA | `middleware('auth')` / `middleware('auth:sanctum')` |
| Authorization | Policy + Middleware | Proje bazlı RBAC → [05-RBAC_PERMISSIONS.md](./05-RBAC_PERMISSIONS.md) |
| Validation | FormRequest | Her endpoint için ayrı Request class |
| Logging | Activity Log | Domain Event → Listener → `activity_logs` tablosu |
| Caching | Redis | Burndown snapshot, proje ayarları |
| Error Handling | Laravel Exception Handler | Genel try-catch yasak, özel exception class'lar |
| Real-time | Laravel Reverb + Echo | Board değişiklikleri, bildirimler |
| File Storage | MinIO (S3-compat) | Laravel Filesystem abstraction |
| Queue | Redis driver | Bildirimler, analytics hesaplama, dosya işleme |
| Scheduler | Laravel Scheduler | Günlük burndown snapshot, cleanup job'ları |

---

## 6. MVP Kapsamı

| Modül | MVP | İleride |
|-------|:---:|:-------:|
| Auth (Email/Password) | ✅ | OAuth (Socialite) |
| Proje Yönetimi (CRUD, Üyelik, RBAC) | ✅ | — |
| Scrum Board (Sprint, Backlog, Estimation) | ✅ | Kanban Board |
| Issue Tracker (Bug, Question, Enhancement) | ✅ | — |
| Analytics (Burndown, Velocity) | ✅ | Lead/Cycle Time |
| In-App Bildirimler | ✅ | Email, Push |
| Dosya Ekleri (MinIO) | ✅ | — |
| Wiki / Dokümantasyon | ❌ | ✅ |

---

**Sonraki:** [02-DOMAIN_MODEL.md](./02-DOMAIN_MODEL.md) — Entity tanımları ve ilişkiler
