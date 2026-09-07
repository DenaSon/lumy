<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_data_graph_can_be_persisted_with_nullable_metrics(): void
    {
        $account = SocialAccount::query()->create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'status' => 'active',
            'provider_payload' => ['source' => 'fixture'],
        ]);

        $content = $account->contents()->create([
            'platform_post_id' => 'post-123',
            'content_type' => 'reel',
            'media_product_type' => 'REELS',
            'analytics_status' => 'available',
            'published_at' => now()->subDay(),
        ]);

        $media = $content->media()->create([
            'type' => 'video',
            'position' => 0,
            'duration_seconds' => 28,
        ]);

        $contentSnapshot = $content->metricSnapshots()->create([
            'captured_at' => now(),
            'snapshot_type' => 'initial',
            'snapshot_window' => '24h',
            'views' => 1000,
            'saves' => null,
            'provider_payload' => ['hydrated' => true],
        ]);

        $accountSnapshot = $account->accountMetricSnapshots()->create([
            'captured_at' => now(),
            'followers_count' => 11588,
            'reach' => 463827,
        ]);

        $demographic = $account->demographicSnapshots()->create([
            'captured_at' => now(),
            'dimension_type' => 'city',
            'dimension' => 'Tabriz',
            'value' => 2243,
            'rank' => 1,
            'is_partial' => true,
        ]);

        $syncRun = $account->syncRuns()->create([
            'provider' => 'zernio',
            'sync_type' => 'contents',
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'discovered_count' => 111,
            'created_count' => 111,
        ]);

        $this->assertTrue($account->is($content->socialAccount));
        $this->assertTrue($content->is($media->content));
        $this->assertTrue($content->is($contentSnapshot->content));
        $this->assertTrue($account->is($accountSnapshot->socialAccount));
        $this->assertTrue($account->is($demographic->socialAccount));
        $this->assertTrue($account->is($syncRun->socialAccount));
        $this->assertNull($contentSnapshot->fresh()->saves);
        $this->assertSame(['source' => 'fixture'], $account->fresh()->provider_payload);
        $this->assertTrue($demographic->fresh()->is_partial);
    }

    public function test_platform_post_id_is_unique_within_a_social_account(): void
    {
        $account = SocialAccount::query()->create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
        ]);

        $account->contents()->create([
            'platform_post_id' => 'same-post',
        ]);

        $this->expectException(QueryException::class);

        $account->contents()->create([
            'platform_post_id' => 'same-post',
        ]);
    }

    public function test_same_platform_post_id_can_exist_on_different_accounts(): void
    {
        $first = SocialAccount::query()->create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'first',
        ]);

        $second = SocialAccount::query()->create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'second',
        ]);

        $first->contents()->create(['platform_post_id' => 'shared-post']);
        $second->contents()->create(['platform_post_id' => 'shared-post']);

        $this->assertDatabaseCount('contents', 2);
    }

    public function test_deleting_a_social_account_cascades_core_domain_records(): void
    {
        $account = SocialAccount::query()->create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
        ]);

        $content = $account->contents()->create([
            'platform_post_id' => 'post-123',
        ]);

        $content->media()->create([
            'type' => 'video',
        ]);

        $content->metricSnapshots()->create([
            'captured_at' => now(),
        ]);

        $account->accountMetricSnapshots()->create([
            'captured_at' => now(),
        ]);

        $account->demographicSnapshots()->create([
            'captured_at' => now(),
            'dimension_type' => 'country',
            'dimension' => 'IR',
            'value' => 1,
        ]);

        $account->syncRuns()->create([
            'provider' => 'zernio',
            'sync_type' => 'contents',
            'started_at' => now(),
        ]);

        $account->delete();

        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertDatabaseCount('contents', 0);
        $this->assertDatabaseCount('content_media', 0);
        $this->assertDatabaseCount('content_metric_snapshots', 0);
        $this->assertDatabaseCount('account_metric_snapshots', 0);
        $this->assertDatabaseCount('demographic_snapshots', 0);
        $this->assertDatabaseCount('sync_runs', 0);
    }
}
