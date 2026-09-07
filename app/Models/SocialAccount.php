<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function accountMetricSnapshots(): HasMany
    {
        return $this->hasMany(AccountMetricSnapshot::class);
    }

    public function demographicSnapshots(): HasMany
    {
        return $this->hasMany(DemographicSnapshot::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
