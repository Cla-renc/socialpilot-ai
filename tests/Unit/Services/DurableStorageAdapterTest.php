<?php

namespace Tests\Unit\Services;

use App\Models\Brand;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\MediaLibraryService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Tests\TestCase;

class DurableStorageAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_s3_disk_resolves_to_aws_s3_v3_adapter(): void
    {
        $disk = Storage::disk('s3');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk->getAdapter());
    }

    public function test_supabase_disk_resolves_to_aws_s3_v3_adapter(): void
    {
        $disk = Storage::disk('supabase');

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk->getAdapter());
    }

    public function test_default_filesystem_disk_is_local(): void
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertSame('local', config('services.media.storage_disk'));
    }

    public function test_media_library_service_uploads_to_configured_disk(): void
    {
        Storage::fake('s3');
        Config::set('services.media.storage_disk', 's3');

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('test_post.jpg', 500, 'image/jpeg');

        $service = app(MediaLibraryService::class);
        $asset = $service->uploadAsset($user, $brand, $file, ['title' => 'Test Post Image']);

        $this->assertInstanceOf(MediaAsset::class, $asset);
        $this->assertSame('s3', $asset->storage_disk);
        $this->assertNotEmpty($asset->storage_path);
        $this->assertStringContainsString("media/{$brand->id}", $asset->storage_path);

        Storage::disk('s3')->assertExists($asset->storage_path);
    }

    public function test_media_library_service_deletes_asset_from_configured_disk(): void
    {
        Storage::fake('s3');
        Config::set('services.media.storage_disk', 's3');

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('test_delete.png', 400, 'image/png');

        $service = app(MediaLibraryService::class);
        $asset = $service->uploadAsset($user, $brand, $file);

        $path = $asset->storage_path;
        Storage::disk('s3')->assertExists($path);

        $service->deleteAsset($asset);

        Storage::disk('s3')->assertMissing($path);
        $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    }

    public function test_local_storage_fallback_operates_when_configured(): void
    {
        Storage::fake('local');
        Config::set('services.media.storage_disk', 'local');

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('local_asset.webp', 300, 'image/webp');

        $service = app(MediaLibraryService::class);
        $asset = $service->uploadAsset($user, $brand, $file);

        $this->assertSame('local', $asset->storage_disk);
        Storage::disk('local')->assertExists($asset->storage_path);
    }

    public function test_upload_rejects_unsupported_mime_type(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('malicious.exe', 50, 'application/x-msdownload');

        $service = app(MediaLibraryService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->uploadAsset($user, $brand, $file);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Config::set('services.media.max_upload_size_mb', 1);

        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->create('large.jpg', 2048, 'image/jpeg');

        $service = app(MediaLibraryService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->uploadAsset($user, $brand, $file);
    }
}
