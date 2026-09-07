<?php

namespace Tests\Feature;

use App\Integrations\Zernio\InstagramSyncService;
use App\Models\AccountMetricSnapshot;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FollowerBaselineSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('zernio.base_url', 'https://zernio.com/api/v1');
        config()->set('zernio.api_key', 'test-api-key');
    }

    public function test_content_analytics_sync_persists_follower_baseline_once_from_account_metadata(): void
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_123',
        ]);

        Http::fake([
            'https://zernio.com/api/v1/analytics*' => Http::response([
                'posts' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 100,
                    'total' => 0,
                    'pages' => 1,
                ],
                'accounts' => [[
                    '_id' => 'account_123',
                    'platform' => 'instagram',
                    'username' => 'lumixo.dev',
                    'followersCount' => 11662,
                    'followersLastUpdated' => '2026-09-07T10:53:29.429Z',
                ]],
            ]),
        ]);

        $service = app(InstagramSyncService::class);

        $service->syncContentAnalytics($account, '2026-09-01', '2026-09-07');
        $service->syncContentAnalytics($account, '2026-09-01', '2026-09-07');

        $this->assertDatabaseCount('account_metric_snapshots', 1);

        $snapshot = AccountMetricSnapshot::query()->firstOrFail();

        $this->assertSame($account->id, $snapshot->social_account_id);
        $this->assertSame('follower_baseline', $snapshot->snapshot_type);
        $this->assertSame(11662, $snapshot->followers_count);
        $this->assertNull($snapshot->followers_gained);
        $this->assertNull($snapshot->followers_lost);
        $this->assertSame('2026-09-07 10:53:29', $snapshot->captured_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-07 10:53:29', $snapshot->provider_updated_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('zernio_analytics_accounts', $snapshot->provider_payload['source']);
        $this->assertSame(11662, $snapshot->provider_payload['account']['followersCount']);
    }
}
