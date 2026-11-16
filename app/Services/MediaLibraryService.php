<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaLibraryService
{
    public function getAssets(User $user, Brand $brand, array $filters = []): LengthAwarePaginator
    {
        $query = MediaAsset::query()
            ->where('brand_id', $brand->id)
            ->where('user_id', $user->id);

        if (! empty($filters['asset_type'])) {
            $query->where('asset_type', $filters['asset_type']);
        }

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        if (! empty($filters['generation_status'])) {
            $query->where('generation_status', $filters['generation_status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $sortDir = ($filters['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sortDir);

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->paginate($perPage);
    }

    public function getAsset(User $user, int $assetId): MediaAsset
    {
        $asset = MediaAsset::findOrFail($assetId);
        abort_unless($asset->user_id === $user->id, 403, 'Unauthorized asset access.');

        return $asset;
    }

    public function updateAsset(MediaAsset $asset, array $data): MediaAsset
    {
        $asset->update(array_intersect_key($data, array_flip(['title', 'metadata', 'platform'])));

        return $asset->fresh();
    }

    public function deleteAsset(MediaAsset $asset): void
    {
        $storagePath = $asset->storage_path;
        $disk        = $asset->storage_disk ?? config('services.media.storage_disk', 'local');

        $asset->delete();

        if ($storagePath) {
            try {
                Storage::disk($disk)->delete($storagePath);
            } catch (\Throwable) {
                // best-effort cleanup
            }
        }
    }

    public function uploadAsset(User $user, Brand $brand, UploadedFile $file, array $params = []): MediaAsset
    {
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');

        $maxMb    = (int) config('services.media.max_upload_size_mb', 20);
        $maxBytes = $maxMb * 1024 * 1024;

        abort_if($file->getSize() > $maxBytes, 422, "File exceeds maximum size of {$maxMb}MB.");

        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/quicktime', 'video/webm',
        ];

        abort_unless(in_array($file->getMimeType(), $allowedMimes, true), 422, 'Unsupported file type.');

        $disk      = config('services.media.storage_disk', 'local');
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $fileName  = $file->getClientOriginalName();

        $mimeType  = $file->getMimeType();
        $assetType = str_starts_with($mimeType, 'video/') ? 'video' : 'image';

        $path = $file->storeAs(
            "media/{$brand->id}",
            uniqid('upload_', true) . '.' . $extension,
            $disk
        );

        return MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'asset_type'        => $assetType,
            'title'             => $params['title'] ?? pathinfo($fileName, PATHINFO_FILENAME),
            'file_name'         => $fileName,
            'file_format'       => $extension,
            'mime_type'         => $mimeType,
            'storage_disk'      => $disk,
            'storage_path'      => $path,
            'public_url'        => Storage::disk($disk)->url($path),
            'file_size'         => $file->getSize(),
            'platform'          => $params['platform'] ?? null,
            'provider'          => 'uploaded',
            'generation_status' => 'completed',
            'metadata'          => $params['metadata'] ?? [],
        ]);
    }
}
