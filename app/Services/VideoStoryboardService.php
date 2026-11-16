<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\MediaStoryboard;
use App\Models\MediaStoryboardScene;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

class VideoStoryboardService
{
    public function __construct(
        protected MediaGenerationService $mediaService,
    ) {}

    public function generateStoryboard(User $user, Brand $brand, array $params): MediaStoryboard
    {
        abort_unless($brand->user_id === $user->id, 403, 'Unauthorized brand access.');

        $engineUrl    = $this->mediaService->getEngineUrl();
        $token        = $this->mediaService->getEngineToken();
        $brandPayload = $this->mediaService->buildBrandPayload($brand);

        $result = null;
        if ($engineUrl && $token) {
            try {
                $response = Http::acceptJson()
                    ->withToken($token)
                    ->timeout(60)
                    ->post(rtrim($engineUrl, '/') . '/internal/v1/media/storyboards/generate', [
                        'brand'            => $brandPayload,
                        'concept'          => $params['concept'] ?? null,
                        'platform'         => $params['platform'] ?? null,
                        'aspect_ratio'     => $params['aspect_ratio'] ?? null,
                        'scene_count'      => $params['scene_count'] ?? 5,
                        'instructions'     => $params['instructions'] ?? null,
                        'content_draft_id' => $params['content_draft_id'] ?? null,
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                }
            } catch (Throwable) {
                // fall through to defaults
            }
        }

        // Create storyboard record
        $storyboard = MediaStoryboard::create([
            'user_id'            => $user->id,
            'brand_id'           => $brand->id,
            'content_draft_id'   => $params['content_draft_id'] ?? null,
            'title'              => $result['title'] ?? ($params['title'] ?? 'Storyboard for ' . $brand->name),
            'concept'            => $result['concept'] ?? ($params['concept'] ?? null),
            'platform'           => $result['platform'] ?? ($params['platform'] ?? null),
            'aspect_ratio'       => $result['aspect_ratio'] ?? ($params['aspect_ratio'] ?? null),
            'estimated_duration' => $result['estimated_duration'] ?? null,
            'status'             => 'draft',
            'metadata'           => $result['metadata'] ?? [],
        ]);

        // Create scene records from result
        $scenes = $result['scenes'] ?? [];
        foreach ($scenes as $index => $sceneData) {
            MediaStoryboardScene::create([
                'storyboard_id'         => $storyboard->id,
                'scene_number'          => $sceneData['scene_number'] ?? ($index + 1),
                'description'           => $sceneData['description'] ?? null,
                'visual_suggestion'     => $sceneData['visual_suggestion'] ?? ($sceneData['visual_direction'] ?? null),
                'narration_text'        => $sceneData['narration_text'] ?? ($sceneData['voiceover'] ?? ($sceneData['dialogue'] ?? null)),
                'on_screen_text'        => $sceneData['on_screen_text'] ?? null,
                'duration_seconds'      => $sceneData['duration_seconds'] ?? ($sceneData['duration'] ?? 5),
                'transition'            => $sceneData['transition'] ?? null,
                'background_music_note' => $sceneData['background_music_note'] ?? ($result['background_music_suggestion'] ?? null),
                'image_asset_id'        => $sceneData['image_asset_id'] ?? null,
                'metadata'              => $sceneData['metadata'] ?? [],
            ]);
        }

        // If no scenes from engine, create placeholder scenes
        if (empty($scenes)) {
            $sceneCount = $params['scene_count'] ?? 3;
            for ($i = 1; $i <= $sceneCount; $i++) {
                MediaStoryboardScene::create([
                    'storyboard_id'     => $storyboard->id,
                    'scene_number'      => $i,
                    'description'       => "Scene {$i} description",
                    'visual_suggestion' => "Scene {$i} visual layout",
                    'duration_seconds'  => 5,
                    'metadata'          => [],
                ]);
            }
        }

        return $storyboard->fresh(['scenes']);
    }

    public function updateStoryboard(MediaStoryboard $storyboard, array $data): MediaStoryboard
    {
        $storyboard->update(array_filter([
            'title'              => $data['title'] ?? null,
            'concept'            => $data['concept'] ?? null,
            'platform'           => $data['platform'] ?? null,
            'aspect_ratio'       => $data['aspect_ratio'] ?? null,
            'estimated_duration' => $data['estimated_duration'] ?? null,
            'status'             => $data['status'] ?? null,
            'metadata'           => $data['metadata'] ?? null,
        ], fn ($v) => ! is_null($v)));

        return $storyboard->fresh(['scenes']);
    }

    public function updateScene(MediaStoryboardScene $scene, array $data): MediaStoryboardScene
    {
        $scene->update(array_filter([
            'description'           => $data['description'] ?? null,
            'visual_suggestion'     => $data['visual_suggestion'] ?? null,
            'narration_text'        => $data['narration_text'] ?? null,
            'on_screen_text'        => $data['on_screen_text'] ?? null,
            'duration_seconds'      => $data['duration_seconds'] ?? null,
            'transition'            => $data['transition'] ?? null,
            'background_music_note' => $data['background_music_note'] ?? null,
            'image_asset_id'        => $data['image_asset_id'] ?? null,
            'metadata'              => $data['metadata'] ?? null,
        ], fn ($v) => ! is_null($v)));

        return $scene->fresh();
    }
}
