<?php

namespace App\Integrations\Zernio;

use App\Models\SocialAccount;
use Carbon\CarbonImmutable;

final class InstagramSyncFreshness
{
    /** @var array<string, array{sync_types: list<string>, stale_after_hours: int}> */
    private const STAGES = [
        'incremental' => [
            'sync_types' => ['contents', 'content_analytics'],
            'stale_after_hours' => 2,
        ],
        'account-insights' => [
            'sync_types' => ['account_analytics'],
            'stale_after_hours' => 24,
        ],
        'demographics' => [
            'sync_types' => ['demographics'],
            'stale_after_hours' => 24,
        ],
        'followers' => [
            'sync_types' => ['followers'],
            'stale_after_hours' => 24,
        ],
    ];

    /**
     * @return array<string, array{due: bool, stale_after_hours: int, last_completed_at: CarbonImmutable|null}>
     */
    public function status(SocialAccount $account, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $syncTypes = collect(self::STAGES)
            ->flatMap(fn (array $stage) => $stage['sync_types'])
            ->unique()
            ->values()
            ->all();

        $latestCompleted = $account->syncRuns()
            ->where('status', 'completed')
            ->whereIn('sync_type', $syncTypes)
            ->selectRaw('sync_type, MAX(finished_at) as last_completed_at')
            ->groupBy('sync_type')
            ->pluck('last_completed_at', 'sync_type');

        $status = [];

        foreach (self::STAGES as $stage => $definition) {
            $timestamps = collect($definition['sync_types'])
                ->map(function (string $syncType) use ($latestCompleted) {
                    $value = $latestCompleted->get($syncType);

                    return $value === null
                        ? null
                        : CarbonImmutable::parse((string) $value, 'UTC');
                });

            $lastCompletedAt = $timestamps->contains(null)
                ? null
                : $timestamps->sortBy(fn (CarbonImmutable $timestamp) => $timestamp->getTimestamp())->first();

            $status[$stage] = [
                'due' => $lastCompletedAt === null
                    || $lastCompletedAt->lessThanOrEqualTo($now->subHours($definition['stale_after_hours'])),
                'stale_after_hours' => $definition['stale_after_hours'],
                'last_completed_at' => $lastCompletedAt,
            ];
        }

        return $status;
    }

    /** @return list<string> */
    public function dueStages(SocialAccount $account, ?CarbonImmutable $now = null): array
    {
        return collect($this->status($account, $now))
            ->filter(fn (array $stage) => $stage['due'])
            ->keys()
            ->values()
            ->all();
    }
}
