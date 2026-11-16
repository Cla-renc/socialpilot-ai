<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BrandService
{
    public function __construct(private readonly DashboardNotificationService $notifications) {}

    public function createBrand(array $data, int $userId): Brand
    {
        $logoPath = $this->storeLogo($data['logo'] ?? null);

        try {
            $brand = DB::transaction(function () use ($data, $userId, $logoPath): Brand {
                $industry = $data['industry'] ?? $data['category'] ?? null;
                $brand = Brand::query()->create([
                    'user_id' => $userId,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'website_url' => $data['website_url'] ?? null,
                    'industry' => $industry,
                    'category' => $industry,
                    'niche' => $data['niche'] ?? null,
                    'audience' => $data['audience'] ?? null,
                    'target_age_group' => $data['target_age_group'] ?? null,
                    'audience_interests' => $this->toArrayField($data['audience_interests'] ?? null),
                    'region' => $data['region'] ?? null,
                    'language' => $data['language'] ?? 'en',
                    'tone' => $data['tone'] ?? null,
                    'status' => $data['status'] ?? 'draft',
                    'logo_path' => $logoPath,
                ]);

                $brand->settings()->create($this->settingsPayload($data));

                return $brand->load('settings');
            });
        } catch (Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            throw $exception;
        }

        $this->notifications->notify(
            User::query()->findOrFail($userId),
            'Brand created',
            $brand->name.' was created successfully.',
            $brand->id,
            '/brands/'.$brand->id,
        );

        return $brand;
    }

    public function updateBrand(Brand $brand, array $data): Brand
    {
        $oldLogoPath = $brand->logo_path;
        $newLogoPath = $this->storeLogo($data['logo'] ?? null);

        try {
            $updated = DB::transaction(function () use ($brand, $data, $newLogoPath): Brand {
                $industry = array_key_exists('industry', $data)
                    ? $data['industry']
                    : ($data['category'] ?? $brand->industry ?? $brand->category);
                $brand->fill([
                    'name' => $this->valueOrCurrent($data, 'name', $brand->name),
                    'description' => $this->valueOrCurrent($data, 'description', $brand->description),
                    'website_url' => $this->valueOrCurrent($data, 'website_url', $brand->website_url),
                    'industry' => $industry,
                    'category' => $industry,
                    'niche' => $this->valueOrCurrent($data, 'niche', $brand->niche),
                    'audience' => $this->valueOrCurrent($data, 'audience', $brand->audience),
                    'target_age_group' => $this->valueOrCurrent($data, 'target_age_group', $brand->target_age_group),
                    'audience_interests' => array_key_exists('audience_interests', $data)
                        ? $this->toArrayField($data['audience_interests'])
                        : $brand->audience_interests,
                    'region' => $this->valueOrCurrent($data, 'region', $brand->region),
                    'language' => $this->valueOrCurrent($data, 'language', $brand->language),
                    'tone' => $this->valueOrCurrent($data, 'tone', $brand->tone),
                    'status' => $this->valueOrCurrent($data, 'status', $brand->status),
                    'logo_path' => $newLogoPath ?? $brand->logo_path,
                ]);
                $brand->save();

                $settings = $brand->settings()->firstOrNew();
                $settings->fill($this->settingsPayload($data, $settings));
                $brand->settings()->save($settings);

                return $brand->fresh(['settings']);
            });
        } catch (Throwable $exception) {
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath && $oldLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return $updated;
    }

    private function settingsPayload(array $data, ?BrandSetting $existing = null): array
    {
        $fields = [
            'brand_voice',
            'writing_style',
            'content_formats',
            'brand_colours',
            'publishing_frequency',
            'timezone',
            'brand_personality',
            'brand_values',
            'brand_keywords',
            'topics_to_avoid',
            'content_restrictions',
            'hashtag_preferences',
            'preferred_publishing_times',
            'marketing_objectives',
            'content_preferences',
        ];
        $payload = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = in_array($field, [
                    'content_formats',
                    'brand_colours',
                    'brand_personality',
                    'brand_values',
                    'brand_keywords',
                    'topics_to_avoid',
                    'content_restrictions',
                    'hashtag_preferences',
                    'preferred_publishing_times',
                    'marketing_objectives',
                ], true) ? $this->toArrayField($data[$field]) : $data[$field];
            } elseif ($existing && $existing->{$field} !== null) {
                $payload[$field] = $existing->{$field};
            } else {
                $payload[$field] = in_array($field, ['publishing_frequency', 'timezone'], true)
                    ? ($field === 'publishing_frequency' ? 'weekly' : 'UTC')
                    : null;
            }

            if ($payload[$field] === null && in_array($field, ['publishing_frequency', 'timezone'], true)) {
                $payload[$field] = $field === 'publishing_frequency' ? 'weekly' : 'UTC';
            }
        }

        return $payload;
    }

    private function storeLogo(mixed $logo): ?string
    {
        if (! $logo instanceof UploadedFile) {
            return null;
        }

        $path = $logo->store('brand-logos', 'public');
        if ($path === false) {
            throw new \RuntimeException('The brand logo could not be stored.');
        }

        return $path;
    }

    private function valueOrCurrent(array $data, string $key, mixed $current): mixed
    {
        return array_key_exists($key, $data) ? $data[$key] : $current;
    }

    private function toArrayField(mixed $value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_string($item) ? trim($item) : $item,
                $value
            ), static fn ($item) => $item !== '' && $item !== null));
        }

        $parts = preg_split('/[,\n]/', (string) $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
