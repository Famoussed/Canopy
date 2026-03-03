# 09 — Infrastructure

Docker Compose yapılandırması, environment yönetimi, deployment süreci ve servis konfigürasyonları.

**İlişkili Dokümanlar:** [Architecture Overview](./01-ARCHITECTURE_OVERVIEW.md) | [Database Schema](./06-DATABASE_SCHEMA.md)

---

## 1. Docker Compose Mimari

```
┌─────────────────────────────────────────────────────────┐
│                      NGINX :80/:443                      │
│                     (Reverse Proxy)                       │
└──────────┬────────────────────────────┬──────────────────┘
           │                            │
    ┌──────▼──────┐             ┌───────▼──────┐
    │   APP :8000  │             │ REVERB :8080 │
    │  (PHP-FPM)   │             │ (WebSocket)  │
    └──────┬───────┘             └──────────────┘
           │
    ┌──────┼──────────────┐
    │      │              │
┌───▼──┐ ┌▼────┐ ┌───────▼──┐
│PGSQL │ │REDIS│ │  MinIO   │
│:5432 │ │:6379│ │:9000/:9001│
└──────┘ └─────┘ └──────────┘
    
    + QUEUE Worker (background)
    + SCHEDULER (cron/schedule:run)
```

---

## 2. Docker Compose Dosyası

```yaml
# docker-compose.yml
version: '3.8'

services:
  # ─────────────── NGINX ───────────────
  nginx:
    image: nginx:1.25-alpine
    ports:
      - "${APP_PORT:-80}:80"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - .:/var/www/html:ro
    depends_on:
      - app
      - reverb
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── APP (PHP-FPM) ───────────────
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile
    volumes:
      - .:/var/www/html
      - vendor:/var/www/html/vendor
      - node_modules:/var/www/html/node_modules
    environment:
      - APP_ENV=${APP_ENV:-production}
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - CACHE_DRIVER=redis
      - SESSION_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - FILESYSTEM_DISK=s3
      - AWS_ENDPOINT=http://minio:9000
      - AWS_ACCESS_KEY_ID=${MINIO_ACCESS_KEY}
      - AWS_SECRET_ACCESS_KEY=${MINIO_SECRET_KEY}
      - AWS_BUCKET=${MINIO_BUCKET:-taiga-files}
      - AWS_USE_PATH_STYLE_ENDPOINT=true
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── REVERB (WebSocket) ───────────────
  reverb:
    build:
      context: .
      dockerfile: docker/Dockerfile
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    environment:
      - REVERB_HOST=0.0.0.0
      - REVERB_PORT=8080
      - REDIS_HOST=redis
    depends_on:
      - redis
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── QUEUE WORKER ───────────────
  queue:
    build:
      context: .
      dockerfile: docker/Dockerfile
    command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
    volumes:
      - .:/var/www/html
    environment:
      - QUEUE_CONNECTION=redis
      - REDIS_HOST=redis
    depends_on:
      - app
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── SCHEDULER ───────────────
  scheduler:
    build:
      context: .
      dockerfile: docker/Dockerfile
    command: >
      sh -c "while true; do
        php artisan schedule:run --verbose --no-interaction;
        sleep 60;
      done"
    volumes:
      - .:/var/www/html
    depends_on:
      - app
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── POSTGRESQL ───────────────
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE:-taiga}
      POSTGRES_USER: ${DB_USERNAME:-taiga}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - pg_data:/var/lib/postgresql/data
    ports:
      - "${DB_PORT:-5432}:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-taiga}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── REDIS ───────────────
  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - taiga-net
    restart: unless-stopped

  # ─────────────── MINIO ───────────────
  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ACCESS_KEY:-minioadmin}
      MINIO_ROOT_PASSWORD: ${MINIO_SECRET_KEY:-minioadmin}
    volumes:
      - minio_data:/data
    ports:
      - "${MINIO_API_PORT:-9000}:9000"
      - "${MINIO_CONSOLE_PORT:-9001}:9001"
    healthcheck:
      test: ["CMD", "mc", "ready", "local"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - taiga-net
    restart: unless-stopped

networks:
  taiga-net:
    driver: bridge

volumes:
  pg_data:
  redis_data:
  minio_data:
  vendor:
  node_modules:
```

---

## 3. Dockerfile

```dockerfile
# docker/Dockerfile
FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    linux-headers \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    supervisor

# PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    pcntl \
    bcmath \
    opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

# App dosyalarını kopyala
COPY . .

# Composer install (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# NPM build
RUN npm ci && npm run build

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

CMD ["php-fpm"]
```

---

## 4. Nginx Konfigürasyonu

```nginx
# docker/nginx.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    # Livewire file uploads
    location /livewire {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # WebSocket proxy
    location /app {
        proxy_pass http://reverb:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 86400;
    }

    # Static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2|ttf)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP processing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 5. Environment Yönetimi

### 5.1 `.env.example`

```dotenv
# ─── Application ───
APP_NAME="Taiga Clone"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
APP_PORT=80

# ─── Database ───
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=taiga
DB_USERNAME=taiga
DB_PASSWORD=secret

# ─── Redis ───
REDIS_HOST=redis
REDIS_PORT=6379

# ─── Cache / Session / Queue ───
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# ─── Broadcasting (Reverb) ───
BROADCAST_DRIVER=reverb
REVERB_APP_ID=taiga-local
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# ─── MinIO / File Storage ───
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=taiga-files
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
MINIO_API_PORT=9000
MINIO_CONSOLE_PORT=9001

# ─── Mail (opsiyonel) ───
MAIL_MAILER=log
```

### 5.2 Dev vs Prod Farkları

| Ayar | Development | Production |
|------|-------------|------------|
| `DB_CONNECTION` | `sqlite` | `pgsql` |
| `APP_DEBUG` | `true` | `false` |
| `APP_ENV` | `local` | `production` |
| `CACHE_DRIVER` | `array` | `redis` |
| `SESSION_DRIVER` | `file` | `redis` |
| `QUEUE_CONNECTION` | `sync` | `redis` |
| `LOG_LEVEL` | `debug` | `warning` |
| `FILESYSTEM_DISK` | `local` | `s3` |

---

## 6. Deployment Adımları

### 6.1 İlk Kurulum

```bash
# 1. Repo'yu klonla
git clone <repo-url> && cd project

# 2. Environment dosyasını hazırla
cp .env.example .env
# .env değerlerini düzenle (production)

# 3. Docker Compose başlat
docker compose up -d --build

# 4. App key oluştur
docker compose exec app php artisan key:generate

# 5. Migration + Seed
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

# 6. MinIO bucket oluştur
docker compose exec minio mc alias set local http://localhost:9000 minioadmin minioadmin
docker compose exec minio mc mb local/taiga-files

# 7. Cache optimize
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan event:cache
```

### 6.2 Güncelleme

```bash
# 1. Kodu güncelle
git pull origin main

# 2. Container'ları rebuild et
docker compose up -d --build

# 3. Migration
docker compose exec app php artisan migrate --force

# 4. Cache temizle ve yeniden oluştur
docker compose exec app php artisan optimize
```

---

## 7. Monitoring & Logging

### 7.1 Log Stratejisi

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'stderr'],
    ],
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'days' => 14,
    ],
],
```

### 7.2 Health Check Endpoint

```php
// routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'services' => [
            'database'  => DB::connection()->getPdo() ? 'up' : 'down',
            'redis'     => Redis::ping() ? 'up' : 'down',
            'queue'     => Cache::has('queue:health') ? 'up' : 'unknown',
        ],
        'timestamp' => now()->toIso8601String(),
    ]);
});
```

### 7.3 Queue Monitoring

```bash
# Horizon kurulu değil, basit monitoring:
docker compose exec app php artisan queue:monitor redis:default --max=100
```

---

## 8. PHP Konfigürasyonu

```ini
; docker/php.ini
[PHP]
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 60

[opcache]
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
```

---

## 9. Local Development (Docker olmadan)

```bash
# 1. Bağımlılıkları kur
composer install
npm install

# 2. SQLite oluştur
touch database/database.sqlite

# 3. Environment
cp .env.example .env
# DB_CONNECTION=sqlite olarak değiştir
php artisan key:generate

# 4. Migration + Seed
php artisan migrate --seed

# 5. Sunucuları başlat (ayrı terminallerde)
php artisan serve          # HTTP :8000
php artisan reverb:start   # WebSocket :8080
php artisan queue:work     # Queue worker
npm run dev                # Vite dev server
```

---

**Önceki:** [08-PROJECT_STRUCTURE.md](./08-PROJECT_STRUCTURE.md)
**Sonraki:** [10-ANALYTICS_ENGINE.md](./10-ANALYTICS_ENGINE.md)
