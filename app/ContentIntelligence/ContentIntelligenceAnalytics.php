<?php

namespace App\ContentIntelligence;

use App\Analytics\ContentDerivedMetrics;
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
 * No aggregate or derived value from this class is persisted.
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
            $dimensions[$dimension] = $this->aggregateDimension($contents, $dimension);
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
        $this->assertDimension($dimension);

        return $this->aggregateDimension($this->contents($account), $dimension);
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
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregateDimension(EloquentCollection $contents, string $dimension): Collection
    {
        $this->assertDimension($dimension);
        $buckets = [];

        foreach ($contents as $content) {
            $attribution = $this->attribution($content, $dimension);

            if ($attribution === null) {
                continue;
            }

            $bucketKey = $attribution['key'];
            $buckets[$bucketKey] ??= [
                'attribution' => $attribution,
                'contents' => [],
            ];
            $buckets[$bucketKey]['contents'][] = $content;
        }

        return collect($buckets)
            ->map(function (array $bucket) use ($dimension) {
                /** @var Collection<int, Content> $bucketContents */
                $bucketContents = collect($bucket['contents']);
                $sampleSize = $bucketContents->count();
                $analyticsSampleSize = $bucketContents
                    ->filter(fn (Content $content) => $content->latestMetricSnapshot !== null)
                    ->count();
                $metrics = [];

                foreach (self::METRICS as $metric => $semantics) {
                    $values = $bucketContents
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

                return [
                    'dimension' => $dimension,
                    'key' => $bucket['attribution']['key'],
                    'label' => $bucket['attribution']['label'],
                    'meta' => $bucket['attribution']['meta'],
                    'sample_size' => $sampleSize,
                    'sample_status' => $this->sampleStatus($sampleSize),
                    'analytics_sample_size' => $analyticsSampleSize,
                    'metrics' => $metrics,
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
