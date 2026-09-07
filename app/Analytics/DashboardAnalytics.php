<?php

namespace App\Analytics;

use App\Models\AccountMetricSnapshot;
use App\Models\Content;
use App\Models\DemographicSnapshot;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class DashboardAnalytics
{
    /**
     * Build the read-only V1 dashboard projection for one Instagram account.
     *
     * No derived value in this projection is persisted. Content KPIs always use
     * the same latest factual snapshot that powers the Content Catalog.
     *
     * @return array<string, mixed>
     */
    public function build(?SocialAccount $account = null): array
    {
        $account ??= $this->defaultAccount();

        if ($account === null) {
            return $this->emptyProjection();
        }

        $contents = $account->contents()
            ->with([
                'annotation',
                'coverMedia',
                'latestMetricSnapshot',
            ])
            ->get();

        $latestAccountInsight = $this->latestAccountInsight($account);
        $followerSnapshots = $this->followerSnapshots($account);
        $latestFollower = $followerSnapshots->first();
        $previousFollower = $followerSnapshots->skip(1)->first();

        $reels = $contents->where('content_type', 'reel')->values();
        $reelsWithAnalytics = $reels->filter(fn (Content $content) => $content->latestMetricSnapshot !== null)->values();
        $advancedReels = $reelsWithAnalytics->filter(function (Content $content) {
            $snapshot = $content->latestMetricSnapshot;

            return $snapshot?->avg_watch_time_ms !== null
                && $snapshot?->video_duration_seconds !== null
                && (float) $snapshot->video_duration_seconds > 0
                && $snapshot?->skip_rate !== null;
        })->values();

        $retentionValues = $advancedReels
            ->map(fn (Content $content) => $content->latestMetricSnapshot?->derivedMetrics()->retentionRate)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value)
            ->values();

        $skipValues = $advancedReels
            ->map(fn (Content $content) => $content->latestMetricSnapshot?->skip_rate)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value)
            ->values();

        $withAnalytics = $contents->filter(fn (Content $content) => $content->latestMetricSnapshot !== null)->count();
        $annotated = $contents->filter(fn (Content $content) => $content->annotation !== null)->count();

        return [
            'account' => $account,
            'account_insight' => $latestAccountInsight,
            'growth' => [
                'followers_count' => $latestFollower?->followers_count,
                'followers_delta' => $latestFollower?->followers_count !== null && $previousFollower?->followers_count !== null
                    ? $latestFollower->followers_count - $previousFollower->followers_count
                    : null,
                'latest_snapshot' => $latestFollower,
                'history' => $followerSnapshots,
            ],
            'content' => [
                'total' => $contents->count(),
                'with_analytics' => $withAnalytics,
                'analytics_coverage' => $this->ratio($withAnalytics, $contents->count()),
                'annotated' => $annotated,
                'annotation_coverage' => $this->ratio($annotated, $contents->count()),
                'top_views' => $this->topContents($contents, fn (Content $content) => $content->latestMetricSnapshot?->views),
                'top_saves' => $this->topContents($contents, fn (Content $content) => $content->latestMetricSnapshot?->saves),
                'top_high_intent' => $this->topContents(
                    $contents,
                    fn (Content $content) => $content->latestMetricSnapshot?->derivedMetrics()->highIntentRate,
                ),
            ],
            'reels' => [
                'total' => $reels->count(),
                'with_analytics' => $reelsWithAnalytics->count(),
                'advanced' => $advancedReels->count(),
                'advanced_coverage' => $this->ratio($advancedReels->count(), $reels->count()),
                'median_retention' => $this->median($retentionValues),
                'median_skip_rate' => $this->median($skipValues),
                'top_views' => $this->topContents($reels, fn (Content $content) => $content->latestMetricSnapshot?->views),
                'top_retention' => $this->topContents(
                    $advancedReels,
                    fn (Content $content) => $content->latestMetricSnapshot?->derivedMetrics()->retentionRate,
                ),
            ],
            'audience' => $this->audience($account),
        ];
    }

    public function defaultAccount(): ?SocialAccount
    {
        $configuredAccountId = trim((string) config('zernio.account_id'));

        if ($configuredAccountId !== '') {
            $configured = SocialAccount::query()
                ->where('provider', 'zernio')
                ->where('provider_account_id', $configuredAccountId)
                ->first();

            if ($configured !== null) {
                return $configured;
            }
        }

        return SocialAccount::query()
            ->where('platform', 'instagram')
            ->latest('id')
            ->first();
    }

    private function latestAccountInsight(SocialAccount $account): ?AccountMetricSnapshot
    {
        return $account->accountMetricSnapshots()
            ->where(function ($query) {
                $query
                    ->whereNotNull('reach')
                    ->orWhereNotNull('views')
                    ->orWhereNotNull('accounts_engaged')
                    ->orWhereNotNull('total_interactions');
            })
            ->latest('captured_at')
            ->latest('id')
            ->first();
    }

    /** @return EloquentCollection<int, AccountMetricSnapshot> */
    private function followerSnapshots(SocialAccount $account): EloquentCollection
    {
        return $account->accountMetricSnapshots()
            ->whereNotNull('followers_count')
            ->orderByRaw('COALESCE(provider_updated_at, captured_at) DESC')
            ->orderByDesc('id')
            ->limit(14)
            ->get();
    }

    /**
     * @param  Collection<int, Content>|EloquentCollection<int, Content>  $contents
     * @return Collection<int, array{content: Content, value: int|float, derived: ContentDerivedMetrics|null}>
     */
    private function topContents(Collection|EloquentCollection $contents, callable $metric, int $limit = 5): Collection
    {
        return $contents
            ->map(function (Content $content) use ($metric) {
                $value = $metric($content);

                if ($value === null) {
                    return null;
                }

                return [
                    'content' => $content,
                    'value' => is_numeric($value) ? (float) $value : $value,
                    'derived' => $content->latestMetricSnapshot?->derivedMetrics(),
                ];
            })
            ->filter()
            ->sortByDesc('value')
            ->take($limit)
            ->values();
    }

    /** @return array<string, mixed> */
    private function audience(SocialAccount $account): array
    {
        $age = $this->latestDemographicDimension($account, 'age');
        $gender = $this->latestDemographicDimension($account, 'gender');
        $city = $this->latestDemographicDimension($account, 'city', highestValueFirst: true);
        $country = $this->latestDemographicDimension($account, 'country', highestValueFirst: true);

        return [
            'age' => $this->withShares($this->sortAge($age)),
            'gender' => $this->withShares($this->sortGender($gender)),
            'cities' => $city->take(5)->values(),
            'countries' => $country->take(5)->values(),
            'captured_at' => collect([$age, $gender, $city, $country])
                ->flatMap(fn (EloquentCollection $rows) => $rows)
                ->max('captured_at'),
        ];
    }

    /** @return EloquentCollection<int, DemographicSnapshot> */
    private function latestDemographicDimension(
        SocialAccount $account,
        string $dimensionType,
        bool $highestValueFirst = false,
    ): EloquentCollection {
        $capturedAt = $account->demographicSnapshots()
            ->where('dimension_type', $dimensionType)
            ->max('captured_at');

        if ($capturedAt === null) {
            return new EloquentCollection;
        }

        $query = $account->demographicSnapshots()
            ->where('dimension_type', $dimensionType)
            ->where('captured_at', $capturedAt);

        if ($highestValueFirst) {
            $query->highestValueFirst();
        }

        return $query->get();
    }

    /**
     * @param  EloquentCollection<int, DemographicSnapshot>  $rows
     * @return Collection<int, array{row: DemographicSnapshot, share: float|null}>
     */
    private function withShares(EloquentCollection $rows): Collection
    {
        $total = $rows->sum('value');

        return $rows->map(fn (DemographicSnapshot $row) => [
            'row' => $row,
            'share' => $this->ratio($row->value, $total),
        ])->values();
    }

    /** @param EloquentCollection<int, DemographicSnapshot> $rows */
    private function sortAge(EloquentCollection $rows): EloquentCollection
    {
        $order = [
            '13-17' => 1,
            '18-24' => 2,
            '25-34' => 3,
            '35-44' => 4,
            '45-54' => 5,
            '55-64' => 6,
            '65+' => 7,
        ];

        return new EloquentCollection(
            $rows->sortBy(fn (DemographicSnapshot $row) => $order[$row->dimension] ?? 999)->values()->all(),
        );
    }

    /** @param EloquentCollection<int, DemographicSnapshot> $rows */
    private function sortGender(EloquentCollection $rows): EloquentCollection
    {
        $order = ['F' => 1, 'M' => 2, 'U' => 3];

        return new EloquentCollection(
            $rows->sortBy(fn (DemographicSnapshot $row) => $order[$row->dimension] ?? 999)->values()->all(),
        );
    }

    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $sorted[$middle];
        }

        return ((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2;
    }

    private function ratio(int|float|null $numerator, int|float|null $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator <= 0) {
            return null;
        }

        return $numerator / $denominator;
    }

    /** @return array<string, mixed> */
    private function emptyProjection(): array
    {
        return [
            'account' => null,
            'account_insight' => null,
            'growth' => [
                'followers_count' => null,
                'followers_delta' => null,
                'latest_snapshot' => null,
                'history' => new EloquentCollection,
            ],
            'content' => [
                'total' => 0,
                'with_analytics' => 0,
                'analytics_coverage' => null,
                'annotated' => 0,
                'annotation_coverage' => null,
                'top_views' => collect(),
                'top_saves' => collect(),
                'top_high_intent' => collect(),
            ],
            'reels' => [
                'total' => 0,
                'with_analytics' => 0,
                'advanced' => 0,
                'advanced_coverage' => null,
                'median_retention' => null,
                'median_skip_rate' => null,
                'top_views' => collect(),
                'top_retention' => collect(),
            ],
            'audience' => [
                'age' => collect(),
                'gender' => collect(),
                'cities' => new EloquentCollection,
                'countries' => new EloquentCollection,
                'captured_at' => null,
            ],
        ];
    }
}
