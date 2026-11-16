<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'brand_id',
        'report_type',
        'date_range_start',
        'date_range_end',
        'period_start',
        'period_end',
        'report_name',
        'filters',
        'format',
        'status',
        'file_path',
        'file_name',
        'generated_at',
        'metadata',
    ];

    protected $appends = [
        'period_start',
        'period_end',
        'report_name',
        'filters',
    ];

    protected $casts = [
        'date_range_start' => 'datetime',
        'date_range_end'   => 'datetime',
        'generated_at'     => 'datetime',
        'metadata'         => 'array',
    ];

    public function getPeriodStartAttribute()
    {
        return $this->date_range_start;
    }

    public function setPeriodStartAttribute($value): void
    {
        $this->attributes['date_range_start'] = $value;
    }

    public function getPeriodEndAttribute()
    {
        return $this->date_range_end;
    }

    public function setPeriodEndAttribute($value): void
    {
        $this->attributes['date_range_end'] = $value;
    }

    public function getReportNameAttribute(): string
    {
        return $this->metadata['report_name'] ?? (ucfirst($this->report_type ?? 'Analytics') . ' Report');
    }

    public function setReportNameAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['report_name'] = $value;
        $this->attributes['metadata'] = json_encode($meta);
    }

    public function getFiltersAttribute(): array
    {
        return $this->metadata['filters'] ?? [];
    }

    public function setFiltersAttribute($value): void
    {
        $meta = $this->metadata ?? [];
        $meta['filters'] = $value;
        $this->attributes['metadata'] = json_encode($meta);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
