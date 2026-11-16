<?php
namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PublishingAttemptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'platform'          => $this->platform,
            'status'            => $this->status,
            'content_type'      => $this->content_type,
            'external_post_id'  => $this->external_post_id,
            'external_post_url' => $this->external_post_url,
            'error_code'        => $this->error_code,
            'error_message'     => $this->error_message,
            'attempt_number'    => $this->attempt_number,
            'is_retryable'      => $this->is_retryable,
            'published_at'      => $this->published_at,
            'brand_id'          => $this->brand_id,
            'scheduled_post_id' => $this->scheduled_post_id,
            'social_account_id' => $this->social_account_id,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            // NEVER include: provider_response, content_payload, tokens
        ];
    }
}
