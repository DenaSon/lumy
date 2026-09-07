<?php

namespace Tests\Feature;

use App\ContentIntelligence\ContentIntelligenceAnalytics;
use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ContentIntelligenceAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_attribution_uses_latest_snapshot_and_honest_metric_samples(): void
    {
        $account = $this->account('account_main');
        $otherAccount = $this->account('account_other');
        $pillar = ContentPillar::create([
            'name' => 'Linux',
            'slug' => 'linux',
            'is_active' => true,
        ]);
        $commands = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Commands',
            'slug' => 'commands',
            'is_active' => true,
        ]);
        $permissions = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Permissions',
            'slug' => 'permissions',
            'is_active' => true,
        ]);

        $first = $this->annotatedContent(
            $account,
            $pillar,
            $commands,
            hookType: 'question',
            hookSource: 'video_overlay',
            ctaType: 'save',
            productionStyle: 'terminal',
        );
        $first->topics()->attach($permissions->id, ['is_primary' => false]);
        $first->hooks()->create([
            'text' => 'Secondary curiosity hook',
            'type' => 'curiosity',
            'source' => 'caption',
            'position' => 1,
            'is_primary' => false,
        ]);
        $this->snapshot($first, '2026-09-01T10:00:00Z', [
            'views' => 9999,
            'reach' => 9000,
            'likes' => 900,
            'comments' => 90,
            'shares' => 90,
            'saves' => 900,
            'avg_watch_time_ms' => 9000,
            'video_duration_seconds' => 10,
            'skip_rate' => 10,
        ]);
        $this->snapshot($first, '2026-09-07T10:00:00Z', [
            'views' => 200,
            'reach' => 150,
            'likes' => 40,
            'comments' => 4,
            'shares' => 10,
            'saves' => 20,
            'avg_watch_time_ms' => 5000,
            'video_duration_seconds' => 10,
            'skip_rate' => 40,
        ]);

        $second = $this->annotatedContent(
            $account,
            $pillar,
            $commands,
            hookType: 'question',
            hookSource: 'video_overlay',
            ctaType: 'save',
            productionStyle: 'terminal',
        );
        $this->snapshot($second, '2026-09-07T10:01:00Z', [
            'views' => 400,
            'reach' => 300,
            'likes' => 40,
            'comments' => 0,
            'shares' => 20,
            'saves' => 20,
            'avg_watch_time_ms' => 7000,
            'video_duration_seconds' => 10,
            'skip_rate' => 50,
        ]);

        $third = $this->annotatedContent(
            $account,
            $pillar,
            $commands,
            hookType: 'question',
            hookSource: 'spoken',
            ctaType: 'save',
            productionStyle: 'screen',
        );
        $this->snapshot($third, '2026-09-07T10:02:00Z', [
            'views' => 100,
            'reach' => null,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
            'avg_watch_time_ms' => null,
            'video_duration_seconds' => null,
            'skip_rate' => null,
        ]);

        $fourth = $this->annotatedContent(
            $account,
            $pillar,
            $permissions,
            hookType: 'result',
            hookSource: 'video_overlay',
            ctaType: 'save',
            productionStyle: 'screen',
        );
        $this->snapshot($fourth, '2026-09-07T10:03:00Z', [
            'views' => 300,
            'reach' => 250,
            'likes' => 30,
            'comments' => 3,
            'shares' => 15,
            'saves' => 30,
            'avg_watch_time_ms' => 6000,
            'video_duration_seconds' => 10,
            'skip_rate' => 45,
        ]);

        $this->annotatedContent(
            $account,
            $pillar,
            $permissions,
            hookType: 'result',
            hookSource: 'spoken',
            ctaType: 'comment',
            productionStyle: 'screen',
        );

        $foreign = $this->annotatedContent(
            $otherAccount,
            $pillar,
            $commands,
            hookType: 'question',
            hookSource: 'video_overlay',
            ctaType: 'save',
            productionStyle: 'terminal',
        );
        $this->snapshot($foreign, '2026-09-07T10:04:00Z', [
            'views' => 50000,
            'reach' => 40000,
            'likes' => 5000,
            'comments' => 500,
            'shares' => 500,
            'saves' => 5000,
            'avg_watch_time_ms' => 10000,
            'video_duration_seconds' => 10,
            'skip_rate' => 5,
        ]);

        $projection = app(ContentIntelligenceAnalytics::class)->forAccount($account);

        $this->assertSame($account->id, $projection['account_id']);
        $this->assertSame(5, $projection['content_count']);

        $question = $projection['dimensions']['primary_hook_type']->firstWhere('key', 'question');
        $this->assertNotNull($question);
        $this->assertSame(3, $question['sample_size']);
        $this->assertSame('exploratory', $question['sample_status']);
        $this->assertSame(3, $question['analytics_sample_size']);
        $this->assertSame(200.0, $question['metrics']['views']['median']);
        $this->assertSame(3, $question['metrics']['views']['sample_size']);
        $this->assertSame('exploratory', $question['metrics']['views']['sample_status']);
        $this->assertSame('descriptive_lifetime', $question['metrics']['views']['comparison_role']);
        $this->assertSame(225.0, $question['metrics']['reach']['median']);
        $this->assertSame(2, $question['metrics']['reach']['sample_size']);
        $this->assertSame('insufficient', $question['metrics']['reach']['sample_status']);
        $this->assertEqualsWithDelta(0.05, $question['metrics']['save_rate']['median'], 0.000001);
        $this->assertEqualsWithDelta(0.10, $question['metrics']['high_intent_rate']['median'], 0.000001);
        $this->assertEqualsWithDelta(0.20, $question['metrics']['engagement_per_view']['median'], 0.000001);
        $this->assertEqualsWithDelta(0.60, $question['metrics']['retention_rate']['median'], 0.000001);
        $this->assertSame(2, $question['metrics']['retention_rate']['sample_size']);
        $this->assertSame('insufficient', $question['metrics']['retention_rate']['sample_status']);
        $this->assertSame(45.0, $question['metrics']['skip_rate']['median']);
        $this->assertSame('provider_percent', $question['metrics']['skip_rate']['kind']);

        $this->assertNull($projection['dimensions']['primary_hook_type']->firstWhere('key', 'curiosity'));

        $commandsGroup = $projection['dimensions']['primary_topic']->firstWhere('key', (string) $commands->id);
        $permissionsGroup = $projection['dimensions']['primary_topic']->firstWhere('key', (string) $permissions->id);
        $this->assertSame(3, $commandsGroup['sample_size']);
        $this->assertSame(2, $permissionsGroup['sample_size']);
        $this->assertSame('Linux', $commandsGroup['meta']['pillar_name']);

        $education = $projection['dimensions']['goal']->firstWhere('key', 'education');
        $this->assertSame(5, $education['sample_size']);
        $this->assertSame(4, $education['analytics_sample_size']);
        $this->assertSame('usable', $education['sample_status']);

        $terminal = $projection['dimensions']['production_style']->firstWhere('key', 'terminal');
        $screen = $projection['dimensions']['production_style']->firstWhere('key', 'screen');
        $this->assertSame(2, $terminal['sample_size']);
        $this->assertSame('insufficient', $terminal['sample_status']);
        $this->assertSame(3, $screen['sample_size']);
        $this->assertSame('exploratory', $screen['sample_status']);

        $saveCta = $projection['dimensions']['cta_type']->firstWhere('key', 'save');
        $this->assertSame(4, $saveCta['sample_size']);
        $this->assertSame('exploratory', $saveCta['sample_status']);
    }

    public function test_unsupported_dimension_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ContentIntelligenceAnalytics::class)->dimension($this->account('invalid_dimension'), 'all_topics');
    }

    private function account(string $providerAccountId): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => $providerAccountId,
        ]);
    }

    private function annotatedContent(
        SocialAccount $account,
        ContentPillar $pillar,
        Topic $primaryTopic,
        string $hookType,
        string $hookSource,
        string $ctaType,
        string $productionStyle,
    ): Content {
        static $sequence = 0;
        $sequence++;

        $content = $account->contents()->create([
            'platform_post_id' => 'intelligence_post_'.$sequence,
            'caption' => 'Intelligence fixture '.$sequence,
            'content_type' => 'reel',
            'analytics_status' => 'available',
            'published_at' => '2026-09-07T08:00:00Z',
        ]);

        $content->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
            'cta_type' => $ctaType,
            'production_style' => $productionStyle,
            'annotated_at' => now(),
        ]);
        $content->topics()->attach($primaryTopic->id, ['is_primary' => true]);
        $content->hooks()->create([
            'text' => 'Primary '.$hookType.' hook',
            'type' => $hookType,
            'source' => $hookSource,
            'position' => 0,
            'is_primary' => true,
        ]);

        return $content;
    }

    /** @param array<string, int|float|null> $metrics */
    private function snapshot(Content $content, string $providerUpdatedAt, array $metrics): void
    {
        $content->metricSnapshots()->create([
            'captured_at' => $providerUpdatedAt,
            'provider_updated_at' => $providerUpdatedAt,
            'snapshot_type' => 'scheduled',
            'snapshot_window' => 'lifetime',
            ...$metrics,
        ]);
    }
}
