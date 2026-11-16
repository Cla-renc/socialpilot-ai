# SOCIALPILOT AI — PERSISTENT MEDIA STORAGE SETUP GUIDE

**Phase:** 7 (AI Media Generation & Storage Management)  
**Security Level:** Tenant-Isolated & Authenticated  
**Storage Drivers:** Local Persistent Disk | S3-Compatible (R2 / MinIO) | Cloudinary  

---

## 1. MEDIA STORAGE ARCHITECTURE

SocialPilot AI manages user-uploaded media and AI-generated visual assets:
- **Image Assets:** Quote cards, motivational graphics, educational carousels, generated brand banners (JPEG, PNG, WebP).
- **Video Assets:** Storyboard scenes, short-form video previews, reels/TikTok templates (MP4, QuickTime).
- **Tenant Isolation:** Every file is stored under a brand-scoped path: `media/{brand_id}/upload_{unique_id}.{ext}`.
- **Access Control:** User authorization is verified before any asset is uploaded, updated, or deleted via `MediaLibraryService`.

---

## 2. THE EPHEMERAL FILESYSTEM RISK

> [!WARNING]
> Public cloud PaaS free tiers (e.g., Render Free Web Services, Heroku, standard container dynos) use **ephemeral filesystems**. Any file saved to local disk is permanently erased when the dyno restarts, sleeps, or deploys a new commit.
>
> You must **never** rely on an ephemeral local disk for production media storage.

---

## 3. ZERO-COST PERSISTENT STORAGE OPTIONS

### Option A: Persistent Local Volume Mount (Docker / Self-Hosted VPS)
When running via Docker Compose or a persistent server:
- Mount the local storage directory to a persistent host volume:
  ```yaml
  volumes:
    - ./storage/app/public:/var/www/html/storage/app/public
  ```
- Configure `.env`:
  ```env
  FILESYSTEM_DISK=public
  MEDIA_STORAGE_DISK=public
  ```
- Create the public symlink: `php artisan storage:link`.

### Option B: Cloudinary (Zero Credit Card Required)
- **Free Allowance:** 25 monthly credits (up to 25 GB storage or 25,000 transformations).
- **Sign Up:** Register at [Cloudinary](https://cloudinary.com/) (zero payment card requested).
- **Integration:** Media assets are uploaded directly via Cloudinary's secure upload API, and the permanent CDN URL (`https://res.cloudinary.com/...`) is saved to the `media_assets` table.

### Option C: S3-Compatible Storage (Cloudflare R2 / Self-Hosted MinIO)
SocialPilot AI supports any S3-compatible object storage via Laravel's native S3 filesystem driver:
```env
MEDIA_STORAGE_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=socialpilot-media
AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

---

## 4. VALIDATION & SECURITY ENFORCEMENT

All media uploads are validated in `app/Services/MediaLibraryService.php`:
1. **Size Limit:** Capped at `MEDIA_MAX_UPLOAD_MB=20` (20 MB). Larger uploads are rejected with HTTP 422.
2. **MIME Whitelist:**
   - Images: `image/jpeg`, `image/png`, `image/webp`, `image/gif`
   - Videos: `video/mp4`, `video/quicktime`, `video/webm`
3. **Tenant Ownership:** Validates `$brand->user_id === $user->id`. Cross-tenant uploads or reads trigger HTTP 403 Forbidden.
4. **Safe Deletion:** Deleting a `MediaAsset` record initiates cleanup of the file on the underlying storage disk before removing the database row.
