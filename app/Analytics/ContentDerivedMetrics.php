<?php

namespace App\Analytics;

use App\Models\ContentMetricSnapshot;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Derived content KPIs calculated from one factual metric snapshot.
 *
 * Rates are returned as ratios, not percentages. For example, 0.037 means 3.7%.
 * Missing provider inputs stay missing: required NULL inputs or a zero/missing
 * denominator produce NULL rather than an invented zero.
 */
final readonly class ContentDerivedMetrics implements Arrayable, JsonSerializable
{
    public function __construct(
        public ?int $interactionCount,
        public ?int $highIntentActions,
        public ?float $likeRate,
        public ?float $commentRate,
        public ?float $shareRate,
        public ?float $saveRate,
        public ?float $highIntentRate,
        public ?float $engagementPerView,
        public ?float $engagementPerReach,
        public ?float $retentionRate,
    ) {}

    public static function fromSnapshot(ContentMetricSnapshot $snapshot): self
    {
        $interactionCount = self::completeSum([
            $snapshot->likes,
            $snapshot->comments,
            $snapshot->shares,
            $snapshot->saves,
        ]);

        $highIntentActions = self::completeSum([
            $snapshot->shares,
            $snapshot->saves,
        ]);

        $videoDurationMs = $snapshot->video_duration_seconds === null
            ? null
            : (float) $snapshot->video_duration_seconds * 1000;

        return new self(
            interactionCount: $interactionCount,
            highIntentActions: $highIntentActions,
            likeRate: self::ratio($snapshot->likes, $snapshot->views),
            commentRate: self::ratio($snapshot->comments, $snapshot->views),
            shareRate: self::ratio($snapshot->shares, $snapshot->views),
            saveRate: self::ratio($snapshot->saves, $snapshot->views),
            highIntentRate: self::ratio($highIntentActions, $snapshot->views),
            engagementPerView: self::ratio($interactionCount, $snapshot->views),
            engagementPerReach: self::ratio($interactionCount, $snapshot->reach),
            retentionRate: self::ratio($snapshot->avg_watch_time_ms, $videoDurationMs),
        );
    }

    /**
     * @return array<string, int|float|null>
     */
    public function toArray(): array
    {
        return [
            'interaction_count' => $this->interactionCount,
            'high_intent_actions' => $this->highIntentActions,
            'like_rate' => $this->likeRate,
            'comment_rate' => $this->commentRate,
            'share_rate' => $this->shareRate,
            'save_rate' => $this->saveRate,
            'high_intent_rate' => $this->highIntentRate,
            'engagement_per_view' => $this->engagementPerView,
            'engagement_per_reach' => $this->engagementPerReach,
            'retention_rate' => $this->retentionRate,
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, int|null>  $values
     */
    private static function completeSum(array $values): ?int
    {
        if (in_array(null, $values, true)) {
            return null;
        }

        return array_sum($values);
    }

    private static function ratio(int|float|null $numerator, int|float|null $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator <= 0) {
            return null;
        }

        return $numerator / $denominator;
    }
}
