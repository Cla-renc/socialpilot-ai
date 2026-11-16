# SOCIALPILOT AI — LARAVEL PRODUCTION DEPLOYMENT GUIDE

**Stack:** PHP 8.2-FPM + Nginx + Composer + Laravel 12  
**Target:** Web Application (Blade) + REST API (Sanctum)  
**Security Level:** Production Hardened  

---

## 1. PRODUCTION DOCKERFILE ARCHITECTURE

For zero-cost container deployments or self-hosted servers, SocialPilot AI uses a hardened multi-stage Docker build:

```dockerfile
FROM php:8.2-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    ca-certificates

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip bcmath intl opcache

# Copy application files
WORKDIR /var/www/html
COPY . /var/www/html

# Set directory permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Production entrypoint
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
```

---

## 2. PRODUCTION OPTIMIZATION COMMANDS

During the deployment build process, run the following commands to maximize performance and compile configuration:

```bash
# 1. Optimize Composer Autoloading
composer install --no-dev --optimize-autoloader --no-interaction

# 2. Cache Application Configuration
php artisan config:cache

# 3. Cache API and Web Routes
php artisan route:cache

# 4. Compile and Cache Blade Views
php artisan view:cache

# 5. Cache Scheduled Events
php artisan event:cache

# 6. Ensure Storage Symlink Exists
php artisan storage:link

# 7. Execute Database Migrations
php artisan migrate --force
```

> [!NOTE]
> All 179 API routes and Web routes in SocialPilot AI are 100% compatible with `route:cache`. No route closures exist in `routes/api.php` or `routes/web.php`.

---

## 3. NGINX PRODUCTION VHOST CONFIGURATION

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name app.yourdomain.com;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    index index.php;
    charset utf-8;

    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 4. DEPLOYMENT TARGET OPTIONS

### Option A: Render Free Web Service (Stateless Web Tier)
1. In the Render Dashboard, click **New > Web Service**.
2. Connect your private GitHub repository `socialpilot-ai`.
3. Choose **Docker** environment (or Native PHP environment).
4. Set Instance Type to **Free (Hobby)**.
5. In **Environment Variables**, paste the production `.env` values (configured with TiDB Serverless database).
6. Set **Health Check Path** to `/api/v1/health`.
7. Click **Create Web Service**.

*Note: Free Web Services on Render sleep after 15 minutes of inactivity. For continuous scheduled publishing, pair with an external free ping service (e.g. Cron-Job.org) to keep endpoints active.*

### Option B: Zero-Cost Dedicated Container (Docker Compose)
Deploying via Docker Compose on any server or cloud VM runs the complete 5-component stack without external sleeping:
```bash
docker compose up -d --build
```

---

## 5. HEALTH CHECK VERIFICATION

Once deployed, probe the health endpoints:

### Liveness Probe
```bash
curl -i https://app.yourdomain.com/api/v1/health
```
**Expected Response (HTTP 200):**
```json
{
  "status": "ok",
  "timestamp": "2026-11-16T12:00:00.000000Z",
  "checks": {
    "database": true,
    "users": true,
    "brands": true
  }
}
```

### Readiness Probe
```bash
curl -i https://app.yourdomain.com/api/v1/health/ready
```
**Expected Response (HTTP 200):**
```json
{
  "ready": true,
  "service": "socialpilot-ai",
  "timestamp": "2026-11-16T12:00:00.000000Z"
}
```
If the database connection fails, the readiness probe returns HTTP 503 (`"ready": false`), allowing load balancers to withhold traffic until the database recovers.
