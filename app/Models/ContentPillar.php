<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPillar extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function annotations(): HasMany
    {
        return $this->hasMany(ContentAnnotation::class, 'primary_pillar_id');
    }
}
