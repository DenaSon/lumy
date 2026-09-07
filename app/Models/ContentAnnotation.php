<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAnnotation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'annotated_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function primaryPillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'primary_pillar_id');
    }
}
