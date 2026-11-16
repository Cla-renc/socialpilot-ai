# SOCIALPILOT AI — PRODUCTION ENVIRONMENT CONFIGURATION

**Date:** November 16, 2026  
**Security Level:** Production Hardened  
**Cost Model:** 100% Free / Zero Unauthorized Cost  

---

## 1. PRODUCTION ENVIRONMENT VARIABLES CHECKLIST

Every production deployment requires an isolated `.env` configuration file populated with secure, deployment-specific values.

> [!CRITICAL]
> Never commit actual production `.env` files to Git. All secrets, keys, and tokens must be injected via your hosting provider's secure environment variable manager or server secret store.

```env
# ==============================================================================
# CORE APPLICATION SETTINGS
# ==============================================================================
APP_NAME="SocialPilot AI"
APP_ENV=production
APP_KEY=base64:GENERATE_NEW_KEY_VIA_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://app.yourdomain.com
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# Force HTTPS on all generated routes and assets behind reverse proxies / CDNs
FORCE_HTTPS=true

# ==============================================================================
# SECURITY, CORS & SANCTUM
# ==============================================================================
# Comma-separated list of allowed frontend domains (restrict to your web & mobile origins)
CORS_ALLOWED_ORIGINS=https://app.yourdomain.com,https://api.yourdomain.com

# Personal access token lifetime in minutes (10080 = 7 days)
SANCTUM_TOKEN_EXPIRATION_MINUTES=10080

# Web session configuration
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# ==============================================================================
# PRODUCTION DATABASE (TiDB Serverless / MySQL 8.0)
# ==============================================================================
DB_CONNECTION=mysql
DB_HOST=gateway01.us-east-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=socialpilot
DB_USERNAME=your_cluster_prefix.root
DB_PASSWORD=YOUR_STRONG_DATABASE_PASSWORD
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt

# ==============================================================================
# QUEUE & CACHE PROCESSING
# ==============================================================================
# Using database driver ensures zero paid Redis or third-party queue dependencies
QUEUE_CONNECTION=database
CACHE_STORE=database

# ==============================================================================
# INTERNAL AI ENGINE BRIDGE (Laravel <-> FastAPI)
# ==============================================================================
# FastAPI AI Engine service endpoint
AI_ENGINE_URL=http://ai-engine:8080
TREND_ENGINE_URL=http://ai-engine:8080

# Shared secret token for authenticating internal requests between Laravel and Python
AI_ENGINE_INTERNAL_TOKEN=GENERATE_64_CHAR_HEX_TOKEN_HERE
TREND_ENGINE_INTERNAL_TOKEN=SAME_64_CHAR_HEX_TOKEN_AS_ABOVE
PUBLISHING_INTERNAL_TOKEN=SAME_64_CHAR_HEX_TOKEN_AS_ABOVE

# ==============================================================================
# STRICT ZERO-COST AI PROVIDER CONFIGURATION
# ==============================================================================
# Primary default provider: internal (heuristic) or groq (free tier)
AI_PROVIDER=groq
AI_MODEL=llama-3.1-8b-instant

# Groq Cloud (Free Tier Verified)
GROQ_API_KEY=YOUR_FREE_GROQ_API_KEY
GROQ_MODEL=llama-3.1-8b-instant
GROQ_BASE_URL=https://api.groq.com/openai/v1

# OpenRouter (Verified Free Model Identifiers Only)
OPENROUTER_API_KEY=YOUR_OPENROUTER_API_KEY
OPENROUTER_MODEL=meta-llama/llama-3.1-8b-instruct:free
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1

# Local Ollama Fallback (Used when self-hosting with local GPU/CPU)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2:3b
TREND_ENABLE_OLLAMA_ANALYSIS=false

# Paid Providers — MUST REMAIN EMPTY UNDER ZERO-COST POLICY
OPENAI_API_KEY=
GEMINI_API_KEY=

# ==============================================================================
# MEDIA STORAGE & FILE MANAGEMENT
# ==============================================================================
# Storage disk: local (with persistent volume) | s3 (for R2 / MinIO / S3)
MEDIA_STORAGE_DISK=local
MEDIA_MAX_UPLOAD_MB=20
MEDIA_GENERATION_TIMEOUT=120

# Optional S3-compatible cloud storage (Cloudflare R2 / AWS S3 / MinIO)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=socialpilot-media
AWS_USE_PATH_STYLE_ENDPOINT=false

# ==============================================================================
# SOCIAL MEDIA PUBLISHING APIS (OAuth 2.0)
# ==============================================================================
# LinkedIn
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI=https://app.yourdomain.com/social/oauth/linkedin/callback

# Meta (Facebook & Instagram)
META_APP_ID=
META_APP_SECRET=
META_REDIRECT_URI=https://app.yourdomain.com/social/oauth/facebook/callback
META_GRAPH_API_VERSION=v22.0

# X (Twitter)
X_CLIENT_ID=
X_CLIENT_SECRET=
X_REDIRECT_URI=https://app.yourdomain.com/social/oauth/x/callback

# TikTok
TIKTOK_CLIENT_KEY=
TIKTOK_CLIENT_SECRET=
TIKTOK_REDIRECT_URI=https://app.yourdomain.com/social/oauth/tiktok/callback

# Google / YouTube
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://app.yourdomain.com/social/oauth/youtube/callback
YOUTUBE_API_KEY=
```

---

## 2. PRODUCTION SECURITY VERIFICATION CHECKLIST

1. **`APP_DEBUG=false`**: Prevents detailed stack traces, environment variables, or database query fragments from ever displaying in browser error screens.
2. **`APP_ENV=production`**: Enforces strict error handling, optimized autoloading, and disablement of debug tools.
3. **`APP_KEY`**: Must be generated specifically for production using `php artisan key:generate --show`. This key encrypts all session cookies, remember tokens, and social account access tokens at rest using AES-256-CBC.
4. **`FORCE_HTTPS=true`**: Automatically instructs Laravel to rewrite all generated URLs, pagination links, asset links, and redirects to `https://`.
5. **Token Generation for Internal Engine:**
   Generate the internal bridge token via OpenSSL:
   ```bash
   openssl rand -hex 32
   ```
   Paste the identical string into `AI_ENGINE_INTERNAL_TOKEN` and `TREND_ENGINE_INTERNAL_TOKEN`.
