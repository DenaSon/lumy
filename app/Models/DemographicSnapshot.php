<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemographicSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'value' => 'integer',
            'rank' => 'integer',
            'provider_position' => 'integer',
            'is_partial' => 'boolean',
            'provider_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeHighestValueFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('value')
            ->orderBy('dimension');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
