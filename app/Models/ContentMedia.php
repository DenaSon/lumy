<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMedia extends Model
{
    protected $table = 'content_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'decimal:3',
            'provider_payload' => 'array',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
