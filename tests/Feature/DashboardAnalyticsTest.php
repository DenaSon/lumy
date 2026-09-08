<?php

namespace Tests\Feature;

use App\Analytics\DashboardAnalytics;
use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_projection_keeps_growth_content_reels_and_audience_semantics_separate(): void
    {
        $account = $this->account();

        $account->accountMetricSnapshots()->create([
            'period_start' => '2026-06-10T00:00:00Z',
            'period_end' => '2026-09-07T23:59:59Z',
            'captured_at' => '2026-09-07T12:00:00Z',
            'reach' => 1000,
            'views' => 2000,
            'accounts_engaged' => 250,
            'total_interactions' => 300,
            'likes' => 120,
            'comments' => 30,
            'saves' => 90,
            'shares' => 50,
            'reposts' => 10,
        ]);

        $account->accountMetricSnapshots()->create([
            'period_start' => '2026-09-06T00:00:00Z',
            'period_end' => '2026-09-06T23:59:59Z',
            'captured_at' => '2026-09-06T00:00:00Z',
            'followers_count' => 980,
            'followers_gained' => 15,
            'followers_lost' => 5,
        ]);

        $account->accountMetricSnapshots()->create([
            'captured_at' => '2026-09-07T10:53:29Z',
            'provider_updated_at' => '2026-09-07T10:53:29Z',
            'snapshot_type' => 'follower_baseline',
            'followers_count' => 1000,
        ]);

        $highIntent = $this->content($account, 'High intent reel', 'reel', 'available', '2026-09-07T08:00:00Z');
        $highIntent->annotation()->create([
            'goal' => 'education',
            'annotated_at' => '2026-09-07T13:00:00Z',
        ]);
        $highIntent->hooks()->create([
            'text' => 'Primary dashboard hook',
            'type' => 'question',
            'source' => 'video_overlay',
            'position' => 0,
            'is_primary' => true,
        ]);
        $this->snapshot($highIntent, [
            'views' => 1000,
            'reach' => 800,
            'likes' => 50,
            'comments' => 10,
            'shares' => 50,
            'saves' => 100,
            'avg_watch_time_ms' => 5000,
            'video_duration_seconds' => 10,
            'skip_rate' => 40,
        ]);

        $mostViewed = $this->content($account, 'Most viewed reel', 'reel', 'available', '2026-09-06T08:00:00Z');
        $this->snapshot($mostViewed, [
            'views' => 2000,
            'reach' => 1500,
            'likes' => 50,
            'comments' => 10,
            'shares' => 50,
            'saves' => 50,
            'avg_watch_time_ms' => 3000,
            'video_duration_seconds' => 10,
            'skip_rate' => 60,
        ]);

        $this->content($account, 'Pending carousel', 'carousel', 'pending', '2026-09-05T08:00:00Z');

        $capturedAt = '2026-09-07T13:30:00Z';
        foreach ([
            ['age', '25-34', 60, false],
            ['age', '35-44', 40, false],
            ['gender', 'F', 5, false],
            ['gender', 'M', 90, false],
            ['gender', 'U', 5, false],
            ['city', 'Tehran', 17, true],
            ['city', 'Tabriz', 23, true],
            ['country', 'DE', 1, true],
            ['country', 'IR', 95, true],
        ] as $row) {
            $account->demographicSnapshots()->create([
                'captured_at' => $capturedAt,
                'dimension_type' => $row[0],
                'dimension' => $row[1],
                'value' => $row[2],
                'rank' => null,
                'provider_position' => 1,
                'is_partial' => $row[3],
            ]);
        }

        $analytics = app(DashboardAnalytics::class);
        $dashboard = $analytics->build($account);
        $core = $analytics->buildCore($account);
        $audience = $analytics->audienceProjection($account);

        $this->assertArrayNotHasKey('audience', $core);
        $this->assertSame($dashboard['content']['total'], $core['content']['total']);
        $this->assertSame($dashboard['reels']['median_skip_rate'], $core['reels']['median_skip_rate']);
        $this->assertSame($dashboard['audience']['captured_at']?->toISOString(), $audience['captured_at']?->toISOString());

        $this->assertSame(1000, $dashboard['growth']['followers_count']);
        $this->assertSame(20, $dashboard['growth']['followers_delta']);
        $this->assertSame(1000, $dashboard['account_insight']->reach);
        $this->assertSame(2000, $dashboard['account_insight']->views);

        $this->assertSame(3, $dashboard['content']['total']);
        $this->assertSame(2, $dashboard['content']['with_analytics']);
        $this->assertEqualsWithDelta(2 / 3, $dashboard['content']['analytics_coverage'], 0.000001);
        $this->assertSame(1, $dashboard['content']['annotated']);
        $this->assertSame(1, $dashboard['content']['primary_hooks']);
        $this->assertEqualsWithDelta(1 / 3, $dashboard['content']['primary_hook_coverage'], 0.000001);
        $this->assertEqualsWithDelta(0.10, $dashboard['content']['median_high_intent'], 0.000001);
        $this->assertSame('Most viewed reel', $dashboard['content']['top_views']->first()['content']->caption);
        $this->assertSame('High intent reel', $dashboard['content']['top_high_intent']->first()['content']->caption);

        $this->assertSame(2, $dashboard['reels']['total']);
        $this->assertSame(2, $dashboard['reels']['advanced']);
        $this->assertEqualsWithDelta(0.4, $dashboard['reels']['median_retention'], 0.000001);
        $this->assertEqualsWithDelta(50.0, $dashboard['reels']['median_skip_rate'], 0.000001);

        $unknown = $dashboard['audience']['gender']->first(fn (array $item) => $item['row']->dimension === 'U');
        $this->assertNotNull($unknown);
        $this->assertEqualsWithDelta(0.05, $unknown['share'], 0.000001);
        $this->assertSame('Tabriz', $dashboard['audience']['cities']->first()->dimension);
        $this->assertSame('IR', $dashboard['audience']['countries']->first()->dimension);
    }

    public function test_dashboard_page_defers_core_and_lazy_loads_audience_without_changing_final_content(): void
    {
        $account = $this->account();

        $account->accountMetricSnapshots()->create([
            'captured_at' => '2026-09-06T10:53:29Z',
            'provider_updated_at' => '2026-09-06T10:53:29Z',
            'snapshot_type' => 'follower_history',
            'followers_count' => 11600,
        ]);

        $account->accountMetricSnapshots()->create([
            'captured_at' => '2026-09-07T10:53:29Z',
            'provider_updated_at' => '2026-09-07T10:53:29Z',
            'snapshot_type' => 'follower_baseline',
            'followers_count' => 11662,
        ]);

        $content = $this->content($account, 'Dashboard reel', 'reel', 'available', '2026-09-07T08:00:00Z');
        $this->snapshot($content, [
            'views' => 1000,
            'reach' => 800,
            'likes' => 20,
            'comments' => 10,
            'shares' => 50,
            'saves' => 100,
            'avg_watch_time_ms' => 5000,
            'video_duration_seconds' => 10,
            'skip_rate' => 40,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('داشبورد Lumy')
            ->assertSee('در حال آماده‌سازی Dashboard...')
            ->assertSee('در حال بارگذاری Audience...')
            ->assertSee('ثبت سریع Hook')
            ->assertDontSee('Dashboard reel');

        Livewire::withoutLazyLoading()
            ->test('pages::panel.index')
            ->assertSee('Median High Intent')
            ->assertSee('Follower trend')
            ->assertSee('نیازمند توجه')
            ->assertSee('محتواهای برتر')
            ->assertSee('آمادگی داده برای Intelligence')
            ->assertSee('خلاصه مخاطب')
            ->assertSee('11,662')
            ->assertSee('Dashboard reel')
            ->assertSee('50.0%')
            ->assertSee('40.0%')
            ->assertSee('href="'.route('content.hooks').'"', false);
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_dashboard',
        ]);
    }

    private function content(
        SocialAccount $account,
        string $caption,
        string $contentType,
        string $analyticsStatus,
        string $publishedAt,
    ): Content {
        static $sequence = 0;
        $sequence++;

        return $account->contents()->create([
            'platform_post_id' => 'dashboard_post_'.$sequence,
            'caption' => $caption,
            'content_type' => $contentType,
            'analytics_status' => $analyticsStatus,
            'published_at' => $publishedAt,
        ]);
    }

    /** @param array<string, int|float> $metrics */
    private function snapshot(Content $content, array $metrics): void
    {
        $content->metricSnapshots()->create([
            'captured_at' => '2026-09-07T14:00:00Z',
            'provider_updated_at' => '2026-09-07T14:00:00Z',
            'snapshot_type' => 'initial',
            'snapshot_window' => 'lifetime',
            ...$metrics,
        ]);
    }
}
