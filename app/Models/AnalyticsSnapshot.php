<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSnapshotBuilder extends Builder
{
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            foreach ($column as $k => $v) {
                if (in_array($k, ['period_start', 'period_end'], true) && is_string($v) && strlen($v) === 10) {
                    $column[$k] = $v . ' 00:00:00';
                }
            }
            return parent::where($column, $operator, $value, $boolean);
        }

        if (in_array($column, ['period_start', 'period_end'], true) && is_string($operator) && strlen($operator) === 10 && $value === null) {
            $operator = $operator . ' 00:00:00';
        } elseif (in_array($column, ['period_start', 'period_end'], true) && is_string($value) && strlen($value) === 10) {
            $value = $value . ' 00:00:00';
        }

        return parent::where($column, $operator, $value, $boolean);
    }
}

class AnalyticsSnapshot extends Model
{
    use HasFactory;

    public function newEloquentBuilder($query): Builder
    {
        return new AnalyticsSnapshotBuilder($query);
    }

    protected $fillable = [
        'brand_id',
        'social_account_id',
        'platform',
        'metric_type',
        'metric_name',
        'metric_value',
        'metric_unit',
        'reporting_period',
        'period_start',
        'period_end',
        'collected_at',
        'source',
        'metadata',
    ];

    protected $casts = [
        'metric_value' => 'float',
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'collected_at' => 'datetime',
        'metadata'     => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', strtolower($platform));
    }

    public function scopeForMetric(Builder $query, string $metricName): Builder
    {
        return $query->where('metric_name', $metricName);
    }

    public function scopeInPeriod(Builder $query, ?\DateTimeInterface $start, ?\DateTimeInterface $end): Builder
    {
        if ($start) {
            $query->where('collected_at', '>=', $start);
        }
        if ($end) {
            $query->where('collected_at', '<=', $end);
        }
        return $query;
    }
}
