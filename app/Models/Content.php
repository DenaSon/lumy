<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

class Content extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'provider_updated_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ContentMedia::class)->orderBy('position');
    }

    public function coverMedia(): HasOne
    {
        return $this->hasOne(ContentMedia::class)->ofMany('position', 'min');
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(ContentMetricSnapshot::class)->orderBy('captured_at');
    }

    public function latestMetricSnapshot(): HasOne
    {
        return $this->hasOne(ContentMetricSnapshot::class)->latestOfMany('provider_updated_at');
    }

    public function hooks(): HasMany
    {
        return $this->hasMany(ContentHook::class)->orderBy('position');
    }

    public function annotation(): HasOne
    {
        return $this->hasOne(ContentAnnotation::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function scopeOrderByLatestMetric(Builder $query, string $metric, string $direction = 'desc'): Builder
    {
        $allowedMetrics = [
            'views',
            'reach',
            'likes',
            'comments',
            'shares',
            'saves',
            'avg_watch_time_ms',
            'skip_rate',
        ];

        if (! in_array($metric, $allowedMetrics, true)) {
            throw new InvalidArgumentException("Unsupported content metric sort: {$metric}");
        }

        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $latestMetric = ContentMetricSnapshot::query()
            ->select($metric)
            ->whereColumn('content_id', 'contents.id')
            ->orderByRaw('COALESCE(provider_updated_at, captured_at) DESC')
            ->orderByDesc('id')
            ->limit(1);

        return $query->orderBy($latestMetric, $direction);
    }
}
