<?php

namespace Tests\Feature;

use App\Integrations\Zernio\InstagramSyncService;
use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZernioRealPayloadShapeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('zernio.base_url', 'https://zernio.com/api/v1');
        config()->set('zernio.api_key', 'test-api-key');
    }

    public function test_real_external_and_analytics_payload_shapes_are_normalized(): void
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_123',
        ]);

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'POST' && str_ends_with($path, '/posts/sync-external') => Http::response([
                    'synced' => ['postsSynced' => 1],
                ]),
                str_ends_with($path, '/posts') => Http::response([
                    'posts' => [[
                        '_id' => 'external_1',
                        'userId' => [
                            '_id' => 'provider_user_1',
                            'email' => 'private@example.test',
                        ],
                        'content' => 'Real payload caption',
                        'mediaProductType' => 'REELS',
                        'scheduledFor' => '2026-09-06T09:34:23.000Z',
                        'status' => 'published',
                        'platforms' => [[
                            'platform' => 'instagram',
                            'status' => 'published',
                            'publishedAt' => '2026-09-06T09:34:23.000Z',
                            'platformPostId' => '18005209193778720',
                            'platformPostUrl' => 'https://www.instagram.com/reel/example/',
                        ]],
                        'mediaItems' => [[
                            'type' => 'video',
                            'url' => 'https://cdn.example.test/reel.mp4',
                            'thumbnail' => 'https://cdn.example.test/reel.jpg',
                        ]],
                        'updatedAt' => '2026-09-07T13:08:00.788Z',
                    ]],
                    'pagination' => [
                        'page' => 1,
                        'limit' => 100,
                        'total' => 1,
                        'pages' => 1,
                    ],
                ]),
                str_ends_with($path, '/analytics') => Http::response([
                    'posts' => [[
                        '_id' => 'external_1',
                        'publishedAt' => '2026-09-06T09:34:23.000Z',
                        'analytics' => [
                            'views' => 167106,
                            'lastUpdated' => '2026-09-07 13:08:00',
                        ],
                        'platforms' => [[
                            'platform' => 'instagram',
                            'platformPostId' => '18005209193778720',
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
                                'reposts' => 0,
                                'videoDurationSeconds' => 28,
                                'engagementRate' => 8.47,
                                'lastUpdated' => '2026-09-07 13:08:00',
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
                default => Http::response(['error' => 'Unexpected test URL: '.$request->url()], 500),
            };
        });

        $service = app(InstagramSyncService::class);
        $contentResult = $service->syncContents($account);
        $analyticsResult = $service->syncContentAnalytics($account, '2026-09-01', '2026-09-07');

        $content = Content::query()->firstOrFail();
        $snapshot = $content->metricSnapshots()->firstOrFail();

        $this->assertSame(1, $contentResult['created_count']);
        $this->assertSame(0, $contentResult['failed_count']);
        $this->assertSame(1, $analyticsResult['created_count']);
        $this->assertSame(0, $analyticsResult['failed_count']);

        $this->assertSame('18005209193778720', $content->platform_post_id);
        $this->assertSame('external_1', $content->provider_post_id);
        $this->assertSame('https://www.instagram.com/reel/example/', $content->permalink);
        $this->assertSame('reel', $content->content_type);
        $this->assertSame('published', $content->platform_status);
        $this->assertSame('2026-09-06 09:34:23', $content->published_at->utc()->format('Y-m-d H:i:s'));
        $this->assertArrayNotHasKey('userId', $content->provider_payload);

        $this->assertSame('available', $content->fresh()->analytics_status);
        $this->assertSame(167106, $snapshot->views);
        $this->assertSame(130225, $snapshot->reach);
        $this->assertSame(8784, $snapshot->avg_watch_time_ms);
        $this->assertSame('50.3000', $snapshot->skip_rate);
        $this->assertNull($snapshot->follows);
    }
}
