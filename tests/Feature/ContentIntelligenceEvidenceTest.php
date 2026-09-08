<?php

namespace Tests\Feature;

use App\ContentIntelligence\ContentIntelligenceAnalytics;
use App\ContentIntelligence\ContentIntelligenceEvidence;
use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentIntelligenceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_is_compared_with_peers_and_behavior_evidence_is_structured(): void
    {
        $account = $this->account();

        foreach (range(1, 5) as $index) {
            $content = $this->content($account, 'curiosity', 'reel');
            $this->snapshot($content, [
                'views' => 100,
                'reach' => 90,
                'likes' => 10,
                'comments' => 0,
                'shares' => 5,
                'saves' => 15,
                'avg_watch_time_ms' => 8000,
                'video_duration_seconds' => 10,
                'skip_rate' => 30,
            ], $index);
        }

        foreach (range(6, 10) as $index) {
            $content = $this->content($account, 'statement', 'carousel');
            $this->snapshot($content, [
                'views' => 100,
                'reach' => 90,
                'likes' => 10,
                'comments' => 0,
                'shares' => 5,
                'saves' => 5,
                'avg_watch_time_ms' => 6000,
                'video_duration_seconds' => 10,
                'skip_rate' => 50,
            ], $index);
        }

        $analysis = app(ContentIntelligenceAnalytics::class)
            ->analyzeDimension($account, 'primary_hook_type');
        $curiosity = $analysis['groups']->firstWhere('key', 'curiosity');

        $this->assertSame(10, $analysis['attributed_sample_size']);
        $this->assertEqualsWithDelta(0.10, $analysis['baseline']['save_rate']['median'], 0.000001);
        $this->assertSame(10, $analysis['baseline']['save_rate']['sample_size']);

        $this->assertEqualsWithDelta(0.15, $curiosity['metrics']['save_rate']['median'], 0.000001);
        $this->assertSame(5, $curiosity['metrics']['save_rate']['sample_size']);
        $this->assertSame(5, $curiosity['comparisons']['save_rate']['peer_sample_size']);
        $this->assertEqualsWithDelta(0.05, $curiosity['comparisons']['save_rate']['peer_median'], 0.000001);
        $this->assertEqualsWithDelta(0.10, $curiosity['comparisons']['save_rate']['delta'], 0.000001);
        $this->assertEqualsWithDelta(2.0, $curiosity['comparisons']['save_rate']['relative_lift'], 0.000001);
        $this->assertSame('above', $curiosity['comparisons']['save_rate']['direction']);
        $this->assertSame('usable', $curiosity['comparisons']['save_rate']['evidence_status']);

        $this->assertSame(30.0, $curiosity['metrics']['skip_rate']['median']);
        $this->assertSame(50.0, $curiosity['comparisons']['skip_rate']['peer_median']);
        $this->assertSame(-20.0, $curiosity['comparisons']['skip_rate']['delta']);
        $this->assertEqualsWithDelta(-0.4, $curiosity['comparisons']['skip_rate']['relative_lift'], 0.000001);
        $this->assertSame('below', $curiosity['comparisons']['skip_rate']['direction']);

        $evidence = app(ContentIntelligenceEvidence::class)
            ->dimension($account, 'primary_hook_type');
        $saveEvidence = $evidence
            ->where('group_key', 'curiosity')
            ->firstWhere('metric', 'save_rate');
        $skipEvidence = $evidence
            ->where('group_key', 'curiosity')
            ->firstWhere('metric', 'skip_rate');

        $this->assertNotNull($saveEvidence);
        $this->assertTrue($saveEvidence['eligible']);
        $this->assertSame('usable', $saveEvidence['evidence_status']);
        $this->assertEqualsWithDelta(0.10, $saveEvidence['overall_median'], 0.000001);
        $this->assertEqualsWithDelta(0.10, $saveEvidence['delta'], 0.000001);
        $this->assertSame('below', $skipEvidence['direction']);
        $this->assertTrue($skipEvidence['eligible']);
        $this->assertNull($evidence->firstWhere('metric', 'views'));
        $this->assertNull($evidence->firstWhere('metric', 'reach'));

        $formatEvidence = app(ContentIntelligenceEvidence::class)
            ->dimension($account, 'format');
        $reelSaveEvidence = $formatEvidence
            ->where('group_key', 'reel')
            ->firstWhere('metric', 'save_rate');

        $this->assertNotNull($reelSaveEvidence);
        $this->assertSame('usable', $reelSaveEvidence['evidence_status']);
        $this->assertTrue($reelSaveEvidence['eligible']);
        $this->assertEqualsWithDelta(0.10, $reelSaveEvidence['delta'], 0.000001);
    }

    public function test_evidence_status_uses_the_weaker_metric_sample_and_zero_peer_baseline_has_no_relative_lift(): void
    {
        $account = $this->account();

        foreach (range(1, 5) as $index) {
            $content = $this->content($account, 'question');
            $this->snapshot($content, [
                'views' => 100,
                'reach' => 100,
                'likes' => 0,
                'comments' => 0,
                'shares' => 10,
                'saves' => 10,
                'avg_watch_time_ms' => 7000,
                'video_duration_seconds' => 10,
                'skip_rate' => 40,
            ], $index);
        }

        foreach (range(6, 10) as $index) {
            $content = $this->content($account, 'result');
            $this->snapshot($content, [
                'views' => 100,
                'reach' => 100,
                'likes' => 0,
                'comments' => 0,
                'shares' => $index <= 8 ? 0 : null,
                'saves' => 0,
                'avg_watch_time_ms' => 5000,
                'video_duration_seconds' => 10,
                'skip_rate' => 60,
            ], $index);
        }

        $analysis = app(ContentIntelligenceAnalytics::class)
            ->analyzeDimension($account, 'primary_hook_type');
        $question = $analysis['groups']->firstWhere('key', 'question');

        $this->assertSame(5, $question['metrics']['high_intent_rate']['sample_size']);
        $this->assertSame(3, $question['comparisons']['high_intent_rate']['peer_sample_size']);
        $this->assertSame('exploratory', $question['comparisons']['high_intent_rate']['evidence_status']);
        $this->assertSame(0.0, $question['comparisons']['high_intent_rate']['peer_median']);
        $this->assertNull($question['comparisons']['high_intent_rate']['relative_lift']);
        $this->assertSame('above', $question['comparisons']['high_intent_rate']['direction']);

        $evidence = app(ContentIntelligenceEvidence::class)
            ->dimension($account, 'primary_hook_type');
        $highIntentEvidence = $evidence
            ->where('group_key', 'question')
            ->firstWhere('metric', 'high_intent_rate');

        $this->assertNotNull($highIntentEvidence);
        $this->assertSame('exploratory', $highIntentEvidence['evidence_status']);
        $this->assertTrue($highIntentEvidence['eligible']);
        $this->assertNull($highIntentEvidence['relative_lift']);
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'evidence_account',
        ]);
    }

    private function content(SocialAccount $account, string $hookType, string $contentType = 'reel'): Content
    {
        static $sequence = 0;
        $sequence++;

        $content = $account->contents()->create([
            'platform_post_id' => 'evidence_post_'.$sequence,
            'caption' => 'Evidence fixture '.$sequence,
            'content_type' => $contentType,
            'analytics_status' => 'available',
            'published_at' => '2026-09-07T08:00:00Z',
        ]);

        $content->hooks()->create([
            'text' => 'Primary '.$hookType.' hook',
            'type' => $hookType,
            'source' => 'video_overlay',
            'position' => 0,
            'is_primary' => true,
        ]);

        return $content;
    }

    /** @param array<string, int|float|null> $metrics */
    private function snapshot(Content $content, array $metrics, int $sequence): void
    {
        $content->metricSnapshots()->create([
            'captured_at' => sprintf('2026-09-07T10:%02d:00Z', $sequence),
            'provider_updated_at' => sprintf('2026-09-07T10:%02d:00Z', $sequence),
            'snapshot_type' => 'scheduled',
            'snapshot_window' => 'lifetime',
            ...$metrics,
        ]);
    }
}
