<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(ContentMetricSnapshot::class)->orderBy('captured_at');
    }
}
