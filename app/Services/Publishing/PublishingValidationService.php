<?php
namespace App\Services\Publishing;

use App\Models\SocialAccount;
use App\Services\Publishing\Providers\SocialPlatformProviderInterface;

class PublishingValidationService
{
    public function validate(SocialAccount $account, array $content, string $platform): array
    {
        try {
            $manager = app(PublishingManager::class);
            $provider = $manager->providerFor($platform);

            $accountValidation = $provider->validateAccount($account);
            if (!$accountValidation['valid']) {
                return ['valid' => false, 'errors' => $accountValidation['errors'] ?? ['Account validation failed'], 'warnings' => []];
            }

            $contentValidation = $provider->validateContent($account, $content);
            return [
                'valid'    => $contentValidation['valid'] ?? true,
                'errors'   => $contentValidation['errors'] ?? [],
                'warnings' => $contentValidation['warnings'] ?? [],
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'errors' => ['Validation exception: ' . $e->getMessage()], 'warnings' => []];
        }
    }
}
