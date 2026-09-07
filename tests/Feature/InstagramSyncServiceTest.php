<?php

namespace Tests\Feature;

use App\Integrations\Zernio\InstagramSyncService;
use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('zernio.base_url', 'https://zernio.com/api/v1');
        config()->set('zernio.api_key', 'test-api-key');
        config()->set('zernio.account_id', 'account_123');
        config()->set('zernio.profile_id', 'profile_123');
    }

    public function test_full_instagram_sync_persists_factual_data_and_snapshots(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            $path = parse_url($url, PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/accounts/account_123/health') => Http::response([
                    'accountId' => 'account_123',
                    'platform' => 'instagram',
                    'username' => 'lumixo.dev',
                    'displayName' => 'Lumixo | Linux, Server & DevOps',
                    'status' => 'healthy',
                    'canFetchAnalytics' => true,
                    'tokenValid' => true,
                    'tokenExpiresAt' => '2026-11-06T10:53:28Z',
                ]),
                $request->method() === 'POST' && str_ends_with($path, '/posts/sync-external') => Http::response([
                    'synced' => [
                        'postsFound' => 1,
                        'postsSynced' => 1,
                        'skipped' => false,
                    ],
                ]),
                str_ends_with($path, '/posts') => Http::response([
                    'posts' => [[
                        '_id' => 'external_1',
                        'platform' => 'instagram',
                        'platformPostId' => '17900000000000001',
                        'platformPostUrl' => 'https://www.instagram.com/reel/example/',
                        'content' => 'Example Reel caption',
                        'mediaProductType' => 'REELS',
                        'mediaType' => 'video',
                        'status' => 'published',
                        'publishedAt' => '2026-09-06T10:00:00Z',
                        'updatedAt' => '2026-09-07T10:00:00Z',
                        'mediaItems' => [[
                            'id' => 'media_1',
                            'type' => 'video',
                            'url' => 'https://cdn.example.com/reel.mp4',
                            'thumbnail' => 'https://cdn.example.com/reel.jpg',
                        ]],
                    ]],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 100,
                        'total' => 1,
                        'pages' => 1,
                    ],
                ]),
                str_ends_with($path, '/analytics/instagram/account-insights') => Http::response([
                    'success' => true,
                    'accountId' => 'account_123',
                    'dateRange' => [
                        'since' => '2026-06-10',
                        'until' => '2026-09-07',
                    ],
                    'metrics' => [
                        'reach' => ['total' => 463827],
                        'views' => ['total' => 1410554],
                        'accounts_engaged' => ['total' => 55266],
                        'total_interactions' => ['total' => 161263],
                        'comments' => ['total' => 8512],
                        'likes' => ['total' => 40725],
                        'saves' => ['total' => 56605],
                        'shares' => ['total' => 27252],
                        'reposts' => ['total' => 760],
                        'follows_and_unfollows' => ['total' => 0],
                        'profile_links_taps' => ['total' => 0],
                    ],
                ]),
                str_ends_with($path, '/analytics/instagram/demographics') => Http::response([
                    'success' => true,
                    'accountId' => 'account_123',
                    'metric' => 'follower_demographics',
                    'timeframe' => 'this_month',
                    'demographics' => [
                        'age' => [['dimension' => '25-34', 'value' => 3979]],
                        'gender' => [['dimension' => 'M', 'value' => 6783]],
                        'city' => [['dimension' => 'Tabriz', 'value' => 2243]],
                        'country' => [['dimension' => 'IR', 'value' => 10608]],
                    ],
                ]),
                str_ends_with($path, '/analytics/instagram/follower-history') => Http::response([
                    'success' => true,
                    'accountId' => 'account_123',
                    'dateRange' => [
                        'since' => '2026-06-10',
                        'until' => '2026-09-07',
                    ],
                    'metrics' => [
                        'follower_count' => [
                            'total' => 11588,
                            'values' => [['date' => '2026-09-07', 'value' => 11588]],
                        ],
                        'followers_gained' => [
                            'total' => 12,
                            'values' => [['date' => '2026-09-07', 'value' => 12]],
                        ],
                        'followers_lost' => [
                            'total' => 3,
                            'values' => [['date' => '2026-09-07', 'value' => 3]],
                        ],
                    ],
                ]),
                str_ends_with($path, '/analytics') => Http::response([
                    'posts' => [[
                        'postId' => 'external_1',
                        'status' => 'published',
                        'content' => 'Example Reel caption',
                        'publishedAt' => '2026-09-06T10:00:00Z',
                        'platform' => 'instagram',
                        'isExternal' => true,
                        'syncStatus' => 'synced',
                        'platformAnalytics' => [[
                            'platform' => 'instagram',
                            'platformPostId' => '17900000000000001',
                            'accountId' => 'account_123',
                            'accountUsername' => 'lumixo.dev',
                            'syncStatus' => 'synced',
                            'platformPostUrl' => 'https://www.instagram.com/reel/example/',
                            'analytics' => [
                                'impressions' => 167106,
                                'reach' => 130225,
                                'likes' => 2494,
                                'comments' => 1345,
                                'shares' => 4116,
                                'saves' => 6197,
                                'views' => 167106,
                                'follows' => null,
                                'igReelsAvgWatchTime' => 8784,
                                'igReelsVideoViewTotalTime' => 1170181476,
                                'reelsSkipRate' => 50.3,
                                'videoDurationSeconds' => 28,
                                'engagementRate' => 8.47,
                                'lastUpdated' => '2026-09-07T10:53:51Z',
                            ],
                        ]],
                    ]],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 100,
                        'total' => 1,
                        'pages' => 1,
                    ],
                ]),
                default => Http::response(['error' => 'Unexpected test URL: '.$url], 500),
            };
        });

        $summary = app(InstagramSyncService::class)->sync('2026-06-10', '2026-09-07');

        $account = SocialAccount::query()->firstOrFail();
        $content = Content::query()->firstOrFail();
        $contentSnapshot = $content->metricSnapshots()->firstOrFail();
        $accountSnapshot = $account->accountMetricSnapshots()->whereNotNull('reach')->firstOrFail();
        $followerSnapshot = $account->accountMetricSnapshots()->whereNotNull('followers_count')->firstOrFail();

        $this->assertSame('account_123', $account->provider_account_id);
        $this->assertSame('lumixo.dev', $account->username);
        $this->assertSame('healthy', $account->status);
        $this->assertNotNull($account->last_synced_at);

        $this->assertSame('17900000000000001', $content->platform_post_id);
        $this->assertSame('external_1', $content->provider_post_id);
        $this->assertSame('reel', $content->content_type);
        $this->assertSame('available', $content->analytics_status);
        $this->assertCount(1, $content->media);

        $this->assertSame(167106, $contentSnapshot->views);
        $this->assertSame(8784, $contentSnapshot->avg_watch_time_ms);
        $this->assertSame('50.3000', $contentSnapshot->skip_rate);
        $this->assertNull($contentSnapshot->follows);
        $this->assertSame('lifetime', $contentSnapshot->snapshot_window);

        $this->assertSame(463827, $accountSnapshot->reach);
        $this->assertSame(1410554, $accountSnapshot->views);
        $this->assertSame(11588, $followerSnapshot->followers_count);
        $this->assertSame(12, $followerSnapshot->followers_gained);
        $this->assertSame(3, $followerSnapshot->followers_lost);

        $this->assertDatabaseCount('demographic_snapshots', 4);
        $this->assertDatabaseCount('sync_runs', 5);
        $this->assertDatabaseMissing('sync_runs', ['status' => 'failed']);

        $this->assertSame(1, $summary['contents']['created_count']);
        $this->assertSame(1, $summary['content_analytics']['created_count']);

        Http::assertSentCount(7);
    }

    public function test_analytics_without_last_updated_is_not_stored_as_real_zero_snapshot(): void
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_123',
        ]);

        $content = $account->contents()->create([
            'platform_post_id' => '17900000000000002',
            'provider_post_id' => 'external_2',
            'content_type' => 'reel',
            'analytics_status' => 'pending',
        ]);

        Http::fake([
            'https://zernio.com/api/v1/analytics*' => Http::response([
                'posts' => [[
                    'postId' => 'external_2',
                    'syncStatus' => 'pending',
                    'platformAnalytics' => [[
                        'platform' => 'instagram',
                        'platformPostId' => '17900000000000002',
                        'syncStatus' => 'pending',
                        'analytics' => [
                            'impressions' => 0,
                            'reach' => 0,
                            'views' => 0,
                            'likes' => 0,
                            'comments' => 0,
                            'shares' => 0,
                            'saves' => 0,
                            'lastUpdated' => null,
                        ],
                    ]],
                ]],
                'pagination' => ['page' => 1, 'pages' => 1],
            ]),
        ]);

        app(InstagramSyncService::class)->syncContentAnalytics(
            $account,
            '2026-06-10',
            '2026-09-07',
        );

        $this->assertDatabaseCount('content_metric_snapshots', 0);
        $this->assertSame('pending', $content->fresh()->analytics_status);
        $this->assertDatabaseHas('sync_runs', [
            'sync_type' => 'content_analytics',
            'status' => 'completed',
        ]);
    }
}
