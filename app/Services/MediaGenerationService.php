<?php

namespace App\Services;

use App\Jobs\ProcessMediaGenerationJob;
use App\Models\Brand;
use App\Models\BrandContentDraft;
use App\Models\MediaAsset;
use App\Models\MediaGenerationJob;
use App\Models\MediaVisualSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MediaGenerationService
{
    public function createImageGenerationJob(User $user, Brand $brand, array $params): MediaGenerationJob
    {
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'content_draft_id'  => $params['content_draft_id'] ?? null,
            'asset_type'        => 'image',
            'title'             => $params['title'] ?? null,
            'platform'          => $params['platform'] ?? null,
            'aspect_ratio'      => $params['aspect_ratio'] ?? null,
            'objective'         => $params['objective'] ?? null,
            'visual_style'      => $params['visual_style'] ?? null,
            'provider'          => $params['provider'] ?? config('services.media.image_provider'),
            'generation_status' => 'pending',
            'generation_params' => $params,
            'storage_disk'      => config('services.media.storage_disk', 'local'),
        ]);

        $job = MediaGenerationJob::create([
            'user_id'          => $user->id,
            'brand_id'         => $brand->id,
            'media_asset_id'   => $asset->id,
            'content_draft_id' => $params['content_draft_id'] ?? null,
            'job_type'         => 'image_generation',
            'provider'         => $params['provider'] ?? config('services.media.image_provider'),
            'status'           => 'queued',
            'progress'         => 0,
            'retry_count'      => 0,
            'max_retries'      => 3,
            'queued_at'        => now(),
            'request_payload'  => $params,
        ]);

        $asset->update(['generation_status' => 'queued']);

        ProcessMediaGenerationJob::dispatch($job)->afterCommit();

        return $job->fresh();
    }

    public function createVideoGenerationJob(User $user, Brand $brand, array $params): MediaGenerationJob
    {
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'content_draft_id'  => $params['content_draft_id'] ?? null,
            'asset_type'        => 'video',
            'title'             => $params['title'] ?? null,
            'platform'          => $params['platform'] ?? null,
            'aspect_ratio'      => $params['aspect_ratio'] ?? null,
            'objective'         => $params['objective'] ?? null,
            'visual_style'      => $params['visual_style'] ?? null,
            'provider'          => $params['provider'] ?? config('services.media.video_provider'),
            'generation_status' => 'pending',
            'generation_params' => $params,
            'storage_disk'      => config('services.media.storage_disk', 'local'),
        ]);

        $job = MediaGenerationJob::create([
            'user_id'          => $user->id,
            'brand_id'         => $brand->id,
            'media_asset_id'   => $asset->id,
            'content_draft_id' => $params['content_draft_id'] ?? null,
            'job_type'         => 'video_generation',
            'provider'         => $params['provider'] ?? config('services.media.video_provider'),
            'status'           => 'queued',
            'progress'         => 0,
            'retry_count'      => 0,
            'max_retries'      => 3,
            'queued_at'        => now(),
            'request_payload'  => $params,
        ]);

        $asset->update(['generation_status' => 'queued']);

        ProcessMediaGenerationJob::dispatch($job)->afterCommit();

        return $job->fresh();
    }

    public function processImageGeneration(MediaGenerationJob $job): void
    {
        $job->update([
            'status'     => 'processing',
            'started_at' => now(),
            'progress'   => 10,
        ]);

        /** @var MediaAsset $asset */
        $asset = MediaAsset::findOrFail($job->media_asset_id);
        $asset->update(['generation_status' => 'processing']);

        $brand        = $job->brand()->with(['settings', 'socialAccounts'])->firstOrFail();
        $brandPayload = $this->buildBrandPayload($brand);

        $visualSetting = MediaVisualSetting::where('brand_id', $brand->id)->first();

        $requestPayload = is_array($job->request_payload)
            ? $job->request_payload
            : (json_decode($job->request_payload ?? '{}', true) ?: []);

        $draft = null;
        if (! empty($requestPayload['content_draft_id'])) {
            $draft = BrandContentDraft::find($requestPayload['content_draft_id']);
        }

        $engineUrl = $this->getEngineUrl();
        $token     = $this->getEngineToken();

        try {
            $payload = [
                'brand'           => $brandPayload,
                'visual_settings' => $visualSetting ? [
                    'visual_identity'                  => $visualSetting->visual_identity,
                    'primary_color'                    => $visualSetting->primary_color,
                    'secondary_color'                  => $visualSetting->secondary_color,
                    'accent_color'                     => $visualSetting->accent_color,
                    'background_color'                 => $visualSetting->background_color,
                    'text_color'                       => $visualSetting->text_color,
                    'primary_font'                     => $visualSetting->primary_font,
                    'secondary_font'                   => $visualSetting->secondary_font,
                    'font_size_base'                   => $visualSetting->font_size_base,
                    'logo_placement'                   => $visualSetting->logo_placement,
                    'preferred_platforms'              => $visualSetting->preferred_platforms,
                    'preferred_aspect_ratios'          => $visualSetting->preferred_aspect_ratios,
                    'image_generation_style_prompt'    => $visualSetting->image_generation_style_prompt,
                    'image_generation_negative_prompt' => $visualSetting->image_generation_negative_prompt,
                    'design_preferences'              => $visualSetting->design_preferences,
                ] : null,
                'objective'      => $requestPayload['objective'] ?? null,
                'visual_style'   => $requestPayload['visual_style'] ?? null,
                'platform'       => $requestPayload['platform'] ?? null,
                'aspect_ratio'   => $requestPayload['aspect_ratio'] ?? null,
                'instructions'   => $requestPayload['instructions'] ?? null,
                'provider'       => $requestPayload['provider'] ?? null,
                'draft'          => $draft ? [
                    'title'   => $draft->title,
                    'hook'    => $draft->hook,
                    'body'    => $draft->body,
                    'caption' => $draft->caption,
                ] : null,
            ];

            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(120)
                ->post(rtrim($engineUrl, '/') . '/internal/v1/media/images/generate', $payload);

            $job->update(['progress' => 60]);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['success']) && $result['success'] === false) {
                    $msg = $result['message'] ?? 'Image generation failed.';
                    throw new RuntimeException($msg);
                }

                $imageUrl  = $result['image_url'] ?? $result['url'] ?? null;
                $base64    = $result['image_base64'] ?? $result['base64'] ?? null;
                $format    = $result['image_format'] ?? $result['format'] ?? 'png';
                $width     = $result['width'] ?? null;
                $height    = $result['height'] ?? null;
                $model     = $result['model'] ?? $result['provider_model'] ?? null;
                $prompt    = $result['prompt_used'] ?? $result['prompt'] ?? null;
                $aspectRatio = $result['aspect_ratio'] ?? ($requestPayload['aspect_ratio'] ?? null);

                $storagePath = null;
                if ($imageUrl || $base64) {
                    $storagePath = $this->storeGeneratedImage($imageUrl, $base64, $format, $brand, $asset);
                }

                $publicUrl = $storagePath
                    ? Storage::disk(config('services.media.storage_disk', 'local'))->url($storagePath)
                    : $imageUrl;

                $asset->update([
                    'storage_path'      => $storagePath,
                    'public_url'        => $publicUrl,
                    'width'             => $width,
                    'height'            => $height,
                    'file_format'       => $format,
                    'aspect_ratio'      => $aspectRatio,
                    'provider_model'    => $model,
                    'generation_prompt' => $prompt,
                    'generation_status' => 'completed',
                ]);

                $job->update([
                    'status'         => 'completed',
                    'progress'       => 100,
                    'completed_at'   => now(),
                    'result_payload' => $result,
                ]);
            } else {
                $error = $response->json('detail') ?? $response->json('message') ?? $response->body();
                throw new RuntimeException("Image generation failed: {$error}");
            }
        } catch (Throwable $e) {
            $asset->update([
                'generation_status' => 'failed',
                'generation_error'  => $e->getMessage(),
            ]);
            $job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            throw $e;
        }
    }

    public function processVideoGeneration(MediaGenerationJob $job): void
    {
        $job->update([
            'status'     => 'processing',
            'started_at' => now(),
            'progress'   => 10,
        ]);

        /** @var MediaAsset $asset */
        $asset = MediaAsset::findOrFail($job->media_asset_id);
        $asset->update(['generation_status' => 'processing']);

        $brand        = $job->brand()->with(['settings', 'socialAccounts'])->firstOrFail();
        $brandPayload = $this->buildBrandPayload($brand);

        $visualSetting  = MediaVisualSetting::where('brand_id', $brand->id)->first();
        $requestPayload = is_array($job->request_payload)
            ? $job->request_payload
            : (json_decode($job->request_payload ?? '{}', true) ?: []);

        $engineUrl = $this->getEngineUrl();
        $token     = $this->getEngineToken();

        try {
            $payload = [
                'brand'           => $brandPayload,
                'visual_settings' => $visualSetting ? [
                    'visual_identity'     => $visualSetting->visual_identity,
                    'primary_color'       => $visualSetting->primary_color,
                    'secondary_color'     => $visualSetting->secondary_color,
                    'accent_color'        => $visualSetting->accent_color,
                    'background_color'    => $visualSetting->background_color,
                    'text_color'          => $visualSetting->text_color,
                    'logo_placement'      => $visualSetting->logo_placement,
                    'design_preferences' => $visualSetting->design_preferences,
                ] : null,
                'objective'       => $requestPayload['objective'] ?? null,
                'visual_style'    => $requestPayload['visual_style'] ?? null,
                'platform'        => $requestPayload['platform'] ?? null,
                'aspect_ratio'    => $requestPayload['aspect_ratio'] ?? null,
                'instructions'    => $requestPayload['instructions'] ?? null,
                'provider'        => $requestPayload['provider'] ?? null,
                'duration'        => $requestPayload['duration'] ?? null,
            ];

            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout(300)
                ->post(rtrim($engineUrl, '/') . '/internal/v1/media/videos/generate', $payload);

            $job->update(['progress' => 60]);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['success']) && $result['success'] === false) {
                    $msg = $result['message'] ?? 'Video generation failed.';
                    throw new RuntimeException($msg);
                }

                $videoUrl = $result['video_url'] ?? $result['url'] ?? null;
                $format   = $result['format'] ?? 'mp4';
                $width    = $result['width'] ?? null;
                $height   = $result['height'] ?? null;
                $model    = $result['model'] ?? $result['provider_model'] ?? null;

                $asset->update([
                    'public_url'        => $videoUrl,
                    'width'             => $width,
                    'height'            => $height,
                    'file_format'       => $format,
                    'provider_model'    => $model,
                    'generation_status' => 'completed',
                ]);

                $job->update([
                    'status'         => 'completed',
                    'progress'       => 100,
                    'completed_at'   => now(),
                    'result_payload' => $result,
                ]);
            } else {
                $error = $response->json('detail') ?? $response->json('message') ?? $response->body();
                throw new RuntimeException("Video generation failed: {$error}");
            }
        } catch (Throwable $e) {
            $asset->update([
                'generation_status' => 'failed',
                'generation_error'  => $e->getMessage(),
            ]);
            $job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            throw $e;
        }
    }

    public function storeGeneratedImage(
        ?string $imageUrl,
        ?string $base64,
        string $format,
        Brand $brand,
        MediaAsset $asset
    ): string {
        $disk = config('services.media.storage_disk', 'local');
        $path = "media/{$brand->id}/{$asset->id}.{$format}";

        if ($base64) {
            $contents = base64_decode($base64);
        } elseif ($imageUrl) {
            $response = Http::timeout(60)->get($imageUrl);
            if (! $response->successful()) {
                throw new RuntimeException("Failed to download image from URL: {$imageUrl}");
            }
            $contents = $response->body();
        } else {
            throw new RuntimeException('No image URL or base64 data provided.');
        }

        Storage::disk($disk)->put($path, $contents);

        return $path;
    }

    public function buildBrandPayload(Brand $brand): array
    {
        $settings = $brand->settings;

        return [
            'id'                  => $brand->id,
            'name'                => $brand->name,
            'industry'            => $brand->industry,
            'niche'               => $brand->niche,
            'description'         => $brand->description,
            'audience'            => $brand->audience,
            'audience_interests'  => $brand->audience_interests,
            'region'              => $brand->region,
            'language'            => $brand->language,
            'tone'                => $brand->tone,
            'connected_platforms' => $brand->socialAccounts->pluck('provider')->unique()->values()->all(),
            'settings'            => $settings ? [
                'brand_voice'          => $settings->brand_voice,
                'writing_style'        => $settings->writing_style,
                'brand_keywords'       => $settings->brand_keywords,
                'content_formats'      => $settings->content_formats,
                'marketing_objectives' => $settings->marketing_objectives,
                'content_preferences'  => $settings->content_preferences,
                'restrictions'         => $settings->restrictions,
                'hashtag_preferences'  => $settings->hashtag_preferences ?? [],
            ] : [],
        ];
    }

    public function getEngineUrl(): string
    {
        return config('services.ai.engine_url') ?: config('services.trends.engine_url', 'http://127.0.0.1:8080');
    }

    public function getEngineToken(): string
    {
        return config('services.ai.internal_token') ?: config('services.trends.internal_token', '');
    }

    public function composeGraphic(User $user, Brand $brand, array $params): MediaAsset
    {
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');

        $visualSetting = MediaVisualSetting::where('brand_id', $brand->id)->first();

        $engineUrl = $this->getEngineUrl();
        $token     = $this->getEngineToken();

        $template    = $params['template'] ?? 'quote_card';
        $aspectRatio = $params['aspect_ratio'] ?? '1:1';

        $payload = [
            'template'             => $template,
            'brand_name'           => $brand->name,
            'headline'             => $params['headline'] ?? null,
            'quote'                => $params['quote'] ?? null,
            'author'               => $params['author'] ?? null,
            'caption'              => $params['caption'] ?? null,
            'call_to_action'       => $params['call_to_action'] ?? null,
            'bullets'              => $params['bullets'] ?? null,
            'primary_color'        => $params['primary_color'] ?? ($visualSetting?->primary_color ?: '#0d1b2a'),
            'secondary_color'      => $params['secondary_color'] ?? ($visualSetting?->secondary_color ?: '#1b263b'),
            'accent_color'         => $params['accent_color'] ?? ($visualSetting?->accent_color ?: '#e0a96d'),
            'background_color'     => $params['background_color'] ?? ($visualSetting?->background_color ?: '#071326'),
            'text_color'           => $params['text_color'] ?? ($visualSetting?->text_color ?: '#ffffff'),
            'primary_font'         => $params['primary_font'] ?? ($visualSetting?->primary_font ?: 'Oswald, sans-serif'),
            'secondary_font'       => $params['secondary_font'] ?? ($visualSetting?->secondary_font ?: 'Roboto, sans-serif'),
            'logo_url'             => $brand->logo_path ? Storage::disk('public')->url($brand->logo_path) : null,
            'logo_placement'       => $visualSetting?->logo_placement ?: 'bottom_right',
            'aspect_ratio'         => $aspectRatio,
            'background_image_url' => $params['background_image_url'] ?? null,
        ];

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(60)
            ->post(rtrim($engineUrl, '/') . '/internal/v1/media/compose', $payload);

        if (! $response->successful()) {
            $err = $response->json('detail') ?? $response->json('message') ?? 'Composition failed.';
            throw new RuntimeException("Composition failed: {$err}");
        }

        $result = $response->json();

        $asset = MediaAsset::create([
            'user_id'           => $user->id,
            'brand_id'          => $brand->id,
            'content_draft_id'  => $params['content_draft_id'] ?? null,
            'asset_type'        => in_array($template, ['quote_card', 'poster', 'graphic'], true) ? $template : 'poster',
            'title'             => $params['title'] ?? ($params['headline'] ?? ($params['quote'] ?? "Branded {$template}")),
            'platform'          => $params['platform'] ?? 'instagram',
            'aspect_ratio'      => $aspectRatio,
            'objective'         => $params['objective'] ?? 'social_post',
            'visual_style'      => $params['visual_style'] ?? ($visualSetting?->visual_identity ?: 'dark_cinematic'),
            'provider'          => 'internal_composer',
            'provider_model'    => 'svg-vector-engine-2.0',
            'generation_status' => 'completed',
            'file_format'       => $result['image_format'] ?? 'svg',
            'mime_type'         => $result['mime_type'] ?? 'image/svg+xml',
            'width'             => $result['width'] ?? 1080,
            'height'            => $result['height'] ?? 1080,
            'generation_prompt' => $result['prompt_used'] ?? null,
            'storage_disk'      => config('services.media.storage_disk', 'local'),
        ]);

        $disk        = config('services.media.storage_disk', 'local');
        $storagePath = "media/{$brand->id}/{$asset->id}.svg";
        $contents    = base64_decode($result['image_base64'] ?? '');

        Storage::disk($disk)->put($storagePath, $contents);
        $publicUrl = Storage::disk($disk)->url($storagePath);

        $asset->update([
            'storage_path' => $storagePath,
            'public_url'   => $publicUrl,
            'file_size'    => strlen($contents),
        ]);

        return $asset->fresh();
    }
}
