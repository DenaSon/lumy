<?php

namespace Tests\Feature;

use App\Models\ContentMetricSnapshot;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDerivedMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_lumy_metrics_from_a_factual_snapshot(): void
    {
        $snapshot = $this->content()->metricSnapshots()->create([
            'captured_at' => '2026-09-07 14:00:00',
            'provider_updated_at' => '2026-09-07 10:53:51',
            'snapshot_type' => 'initial',
            'snapshot_window' => 'lifetime',
            'views' => 167106,
            'reach' => 130225,
            'likes' => 2494,
            'comments' => 1345,
            'shares' => 4116,
            'saves' => 6197,
            'avg_watch_time_ms' => 8784,
            'video_duration_seconds' => 28,
            'provider_engagement_rate' => 99.999,
        ]);

        $metrics = $snapshot->derivedMetrics();

        $this->assertSame(14152, $metrics->interactionCount);
        $this->assertSame(10313, $metrics->highIntentActions);
        $this->assertEqualsWithDelta(2494 / 167106, $metrics->likeRate, 0.000000000001);
        $this->assertEqualsWithDelta(1345 / 167106, $metrics->commentRate, 0.000000000001);
        $this->assertEqualsWithDelta(4116 / 167106, $metrics->shareRate, 0.000000000001);
        $this->assertEqualsWithDelta(6197 / 167106, $metrics->saveRate, 0.000000000001);
        $this->assertEqualsWithDelta(10313 / 167106, $metrics->highIntentRate, 0.000000000001);
        $this->assertEqualsWithDelta(14152 / 167106, $metrics->engagementPerView, 0.000000000001);
        $this->assertEqualsWithDelta(14152 / 130225, $metrics->engagementPerReach, 0.000000000001);
        $this->assertEqualsWithDelta(8784 / 28000, $metrics->retentionRate, 0.000000000001);

        $this->assertSame([
            'interaction_count',
            'high_intent_actions',
            'like_rate',
            'comment_rate',
            'share_rate',
            'save_rate',
            'high_intent_rate',
            'engagement_per_view',
            'engagement_per_reach',
            'retention_rate',
        ], array_keys($metrics->toArray()));
    }

    public function test_missing_inputs_and_zero_denominators_do_not_become_invented_rates(): void
    {
        $snapshot = $this->content()->metricSnapshots()->create([
            'captured_at' => '2026-09-07 14:00:00',
            'provider_updated_at' => '2026-09-07 10:53:51',
            'views' => 100,
            'reach' => 100,
            'likes' => 0,
            'comments' => 0,
            'shares' => null,
            'saves' => 0,
            'avg_watch_time_ms' => 0,
            'video_duration_seconds' => 10,
        ]);

        $metrics = $snapshot->derivedMetrics();

        $this->assertSame(0.0, $metrics->likeRate);
        $this->assertSame(0.0, $metrics->commentRate);
        $this->assertSame(0.0, $metrics->saveRate);
        $this->assertNull($metrics->shareRate);
        $this->assertNull($metrics->interactionCount);
        $this->assertNull($metrics->highIntentActions);
        $this->assertNull($metrics->highIntentRate);
        $this->assertNull($metrics->engagementPerView);
        $this->assertNull($metrics->engagementPerReach);
        $this->assertSame(0.0, $metrics->retentionRate);

        $zeroDenominator = new ContentMetricSnapshot([
            'views' => 0,
            'reach' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
            'avg_watch_time_ms' => 0,
            'video_duration_seconds' => 0,
        ]);

        $zeroMetrics = $zeroDenominator->derivedMetrics();

        $this->assertNull($zeroMetrics->likeRate);
        $this->assertNull($zeroMetrics->saveRate);
        $this->assertNull($zeroMetrics->highIntentRate);
        $this->assertNull($zeroMetrics->engagementPerView);
        $this->assertNull($zeroMetrics->engagementPerReach);
        $this->assertNull($zeroMetrics->retentionRate);
    }

    public function test_content_exposes_the_snapshot_with_the_latest_provider_observation(): void
    {
        $content = $this->content();

        $content->metricSnapshots()->create([
            'captured_at' => '2026-09-07 15:00:00',
            'provider_updated_at' => '2026-09-07 10:00:00',
            'views' => 100,
        ]);

        $content->metricSnapshots()->create([
            'captured_at' => '2026-09-07 14:00:00',
            'provider_updated_at' => '2026-09-07 11:00:00',
            'views' => 200,
        ]);

        $this->assertSame(200, $content->fresh()->latestMetricSnapshot->views);
        $this->assertSame('2026-09-07 11:00:00', $content->fresh()->latestMetricSnapshot->provider_updated_at->utc()->format('Y-m-d H:i:s'));
    }

    private function content()
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_123',
        ]);

        return $account->contents()->create([
            'platform_post_id' => fake()->unique()->numerify('18###############'),
            'content_type' => 'reel',
            'analytics_status' => 'available',
        ]);
    }
}
