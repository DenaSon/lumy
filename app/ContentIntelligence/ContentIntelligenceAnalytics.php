<?php

namespace App\ContentIntelligence;

use App\Models\Content;
use App\Models\ContentHook;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Read-only Content Intelligence aggregation for one social account.
 *
 * Each content may contribute at most once to each primary-attribution dimension.
 * Metrics are calculated from that content's single latest factual snapshot.
 * No aggregate, comparison, or derived value from this class is persisted.
 */
final class ContentIntelligenceAnalytics
{
    /** @var list<string> */
    public const DIMENSIONS = [
        'primary_hook_type',
        'primary_hook_source',
        'primary_topic',
        'primary_pillar',
        'goal',
        'cta_type',
        'production_style',
    ];

    /**
     * Metric kinds are presentation semantics, while comparison roles describe
     * whether a metric is suitable for cross-content behavioral comparison in V1.
     * Lifetime scale metrics remain descriptive until age-normalized snapshots exist.
     *
     * @var array<string, array{kind: string, comparison_role: string}>
     */
    private const METRICS = [
        'views' => ['kind' => 'count', 'comparison_role' => 'descriptive_lifetime'],
        'reach' => ['kind' => 'count', 'comparison_role' => 'descriptive_lifetime'],
        'like_rate' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'comment_rate' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'share_rate' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'save_rate' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'high_intent_rate' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'engagement_per_view' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'engagement_per_reach' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'retention_rate' => ['kind' => 'ratio', 'comparison_role' => 'behavior'],
        'skip_rate' => ['kind' => 'provider_percent', 'comparison_role' => 'behavior'],
    ];

    /**
     * @return array{
     *     account_id: int,
     *     content_count: int,
     *     dimensions: array<string, Collection<int, array<string, mixed>>>
     * }
     */
    public function forAccount(SocialAccount $account): array
    {
        $contents = $this->contents($account);
        $dimensions = [];

        foreach (self::DIMENSIONS as $dimension) {
            $dimensions[$dimension] = $this->analyzeDimensionContents($contents, $dimension)['groups'];
        }

        return [
            'account_id' => $account->id,
            'content_count' => $contents->count(),
            'dimensions' => $dimensions,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function dimension(SocialAccount $account, string $dimension): Collection
    {
        return $this->analyzeDimension($account, $dimension)['groups'];
    }

    /**
     * Return one dimension with its inclusive overall baseline and group-vs-peer
     * comparisons. The peer baseline excludes the current group, so a group is
     * never compared partly against itself.
     *
     * @return array{
     *     dimension: string,
     *     attributed_sample_size: int,
     *     baseline: array<string, array<string, mixed>>,
     *     groups: Collection<int, array<string, mixed>>
     * }
     */
    public function analyzeDimension(SocialAccount $account, string $dimension): array
    {
        $this->assertDimension($dimension);

        return $this->analyzeDimensionContents($this->contents($account), $dimension);
    }

    /** @return EloquentCollection<int, Content> */
    private function contents(SocialAccount $account): EloquentCollection
    {
        return $account->contents()
            ->with([
                'annotation.primaryPillar',
                'hooks',
                'topics.pillar',
                'latestMetricSnapshot',
            ])
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Content>  $contents
     * @return array{
     *     dimension: string,
     *     attributed_sample_size: int,
     *     baseline: array<string, array<string, mixed>>,
     *     groups: Collection<int, array<string, mixed>>
     * }
     */
    private function analyzeDimensionContents(EloquentCollection $contents, string $dimension): array
    {
        $this->assertDimension($dimension);
        $buckets = [];
        $attributedContents = collect();

        foreach ($contents as $content) {
            $attribution = $this->attribution($content, $dimension);

            if ($attribution === null) {
                continue;
            }

            $attributedContents->push($content);
            $bucketKey = $attribution['key'];
            $buckets[$bucketKey] ??= [
                'attribution' => $attribution,
                'contents' => [],
            ];
            $buckets[$bucketKey]['contents'][] = $content;
        }

        $baseline = $this->summarizeMetrics($attributedContents);

        $groups = collect($buckets)
            ->map(function (array $bucket) use ($dimension, $attributedContents) {
                /** @var Collection<int, Content> $bucketContents */
                $bucketContents = collect($bucket['contents']);
                $bucketContentIds = $bucketContents->pluck('id')->all();
                $peerContents = $attributedContents
                    ->reject(fn (Content $content) => in_array($content->id, $bucketContentIds, true))
                    ->values();
                $sampleSize = $bucketContents->count();
                $analyticsSampleSize = $bucketContents
                    ->filter(fn (Content $content) => $content->latestMetricSnapshot !== null)
                    ->count();
                $metrics = $this->summarizeMetrics($bucketContents);
                $peerMetrics = $this->summarizeMetrics($peerContents);
                $comparisons = [];

                foreach (self::METRICS as $metric => $_semantics) {
                    $comparisons[$metric] = $this->compareMetric(
                        $metrics[$metric],
                        $peerMetrics[$metric],
                    );
                }

                return [
                    'dimension' => $dimension,
                    'key' => $bucket['attribution']['key'],
                    'label' => $bucket['attribution']['label'],
                    'meta' => $bucket['attribution']['meta'],
                    'sample_size' => $sampleSize,
                    'sample_status' => $this->sampleStatus($sampleSize),
                    'analytics_sample_size' => $analyticsSampleSize,
                    'peer_sample_size' => $peerContents->count(),
                    'metrics' => $metrics,
                    'comparisons' => $comparisons,
                ];
            })
            ->sort(function (array $left, array $right) {
                $sampleComparison = $right['sample_size'] <=> $left['sample_size'];

                if ($sampleComparison !== 0) {
                    return $sampleComparison;
                }

                return strcasecmp((string) $left['label'], (string) $right['label']);
            })
            ->values();

        return [
            'dimension' => $dimension,
            'attributed_sample_size' => $attributedContents->count(),
            'baseline' => $baseline,
            'groups' => $groups,
        ];
    }

    /**
     * @param  Collection<int, Content>  $contents
     * @return array<string, array{median: float|null, sample_size: int, sample_status: string, kind: string, comparison_role: string}>
     */
    private function summarizeMetrics(Collection $contents): array
    {
        $metrics = [];

        foreach (self::METRICS as $metric => $semantics) {
            $values = $contents
                ->map(fn (Content $content) => $this->metricValue($content, $metric))
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value) => (float) $value)
                ->values();
            $metricSampleSize = $values->count();

            $metrics[$metric] = [
                'median' => $this->median($values),
                'sample_size' => $metricSampleSize,
                'sample_status' => $this->sampleStatus($metricSampleSize),
                ...$semantics,
            ];
        }

        return $metrics;
    }

    /**
     * @param  array{median: float|null, sample_size: int, sample_status: string, kind: string, comparison_role: string}  $group
     * @param  array{median: float|null, sample_size: int, sample_status: string, kind: string, comparison_role: string}  $peer
     * @return array{
     *     peer_median: float|null,
     *     peer_sample_size: int,
     *     peer_sample_status: string,
     *     delta: float|null,
     *     relative_lift: float|null,
     *     direction: string|null,
     *     evidence_status: string
     * }
     */
    private function compareMetric(array $group, array $peer): array
    {
        $groupMedian = $group['median'];
        $peerMedian = $peer['median'];
        $delta = $groupMedian !== null && $peerMedian !== null
            ? $groupMedian - $peerMedian
            : null;

        return [
            'peer_median' => $peerMedian,
            'peer_sample_size' => $peer['sample_size'],
            'peer_sample_status' => $peer['sample_status'],
            'delta' => $delta,
            'relative_lift' => $delta !== null && $peerMedian != 0.0
                ? $delta / abs($peerMedian)
                : null,
            'direction' => $this->direction($delta),
            'evidence_status' => $this->comparisonStatus(
                $group['sample_size'],
                $peer['sample_size'],
                $groupMedian,
                $peerMedian,
            ),
        ];
    }

    /** @return array{key: string, label: string, meta: array<string, mixed>}|null */
    private function attribution(Content $content, string $dimension): ?array
    {
        $primaryHook = $content->hooks
            ->first(fn (ContentHook $hook) => $hook->is_primary);
        $primaryTopic = $content->topics
            ->first(fn (Topic $topic) => (bool) $topic->pivot->is_primary);
        $annotation = $content->annotation;

        return match ($dimension) {
            'primary_hook_type' => $this->textAttribution($primaryHook?->type),
            'primary_hook_source' => $this->textAttribution($primaryHook?->source),
            'primary_topic' => $primaryTopic === null ? null : [
                'key' => (string) $primaryTopic->id,
                'label' => $primaryTopic->name,
                'meta' => [
                    'topic_id' => $primaryTopic->id,
                    'pillar_id' => $primaryTopic->content_pillar_id,
                    'pillar_name' => $primaryTopic->pillar?->name,
                ],
            ],
            'primary_pillar' => $annotation?->primaryPillar === null ? null : [
                'key' => (string) $annotation->primaryPillar->id,
                'label' => $annotation->primaryPillar->name,
                'meta' => [
                    'pillar_id' => $annotation->primaryPillar->id,
                    'slug' => $annotation->primaryPillar->slug,
                ],
            ],
            'goal' => $this->textAttribution($annotation?->goal),
            'cta_type' => $this->textAttribution($annotation?->cta_type),
            'production_style' => $this->textAttribution($annotation?->production_style),
            default => throw new InvalidArgumentException("Unsupported Content Intelligence dimension: {$dimension}"),
        };
    }

    /** @return array{key: string, label: string, meta: array<string, mixed>}|null */
    private function textAttribution(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return [
            'key' => $value,
            'label' => $value,
            'meta' => [],
        ];
    }

    private function metricValue(Content $content, string $metric): int|float|null
    {
        $snapshot = $content->latestMetricSnapshot;

        if ($snapshot === null) {
            return null;
        }

        $derived = $snapshot->derivedMetrics();

        return match ($metric) {
            'views' => $snapshot->views,
            'reach' => $snapshot->reach,
            'like_rate' => $derived->likeRate,
            'comment_rate' => $derived->commentRate,
            'share_rate' => $derived->shareRate,
            'save_rate' => $derived->saveRate,
            'high_intent_rate' => $derived->highIntentRate,
            'engagement_per_view' => $derived->engagementPerView,
            'engagement_per_reach' => $derived->engagementPerReach,
            'retention_rate' => $derived->retentionRate,
            'skip_rate' => $snapshot->skip_rate === null ? null : (float) $snapshot->skip_rate,
            default => throw new InvalidArgumentException("Unsupported Content Intelligence metric: {$metric}"),
        };
    }

    private function sampleStatus(int $sampleSize): string
    {
        if ($sampleSize < 3) {
            return 'insufficient';
        }

        if ($sampleSize < 5) {
            return 'exploratory';
        }

        return 'usable';
    }

    private function comparisonStatus(
        int $groupSampleSize,
        int $peerSampleSize,
        ?float $groupMedian,
        ?float $peerMedian,
    ): string {
        if ($groupMedian === null || $peerMedian === null) {
            return 'insufficient';
        }

        return $this->sampleStatus(min($groupSampleSize, $peerSampleSize));
    }

    private function direction(?float $delta): ?string
    {
        if ($delta === null) {
            return null;
        }

        if (abs($delta) < 0.000000000001) {
            return 'same';
        }

        return $delta > 0 ? 'above' : 'below';
    }

    /** @param Collection<int, float> $values */
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

    private function assertDimension(string $dimension): void
    {
        if (! in_array($dimension, self::DIMENSIONS, true)) {
            throw new InvalidArgumentException("Unsupported Content Intelligence dimension: {$dimension}");
        }
    }
}
