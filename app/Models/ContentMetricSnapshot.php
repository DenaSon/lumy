<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMetricSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'provider_updated_at' => 'datetime',
            'skip_rate' => 'decimal:4',
            'video_duration_seconds' => 'decimal:3',
            'provider_engagement_rate' => 'decimal:6',
            'provider_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
