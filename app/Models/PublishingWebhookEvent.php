<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublishingWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'event_type',
        'external_event_id',
        'payload',
        'signature_valid',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'boolean',
        'processed_at'    => 'datetime',
    ];
}
