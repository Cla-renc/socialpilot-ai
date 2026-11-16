<?php
namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PublishingNotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'type'                   => $this->notification_type ?? $this->type,
            'title'                  => $this->title,
            'body'                   => $this->message ?? $this->body,
            'platform'               => $this->platform,
            'brand_id'               => $this->brand_id,
            'publishing_attempt_id'  => $this->publishing_attempt_id,
            'read_at'                => $this->read_at,
            'created_at'             => $this->created_at,
        ];
    }
}
