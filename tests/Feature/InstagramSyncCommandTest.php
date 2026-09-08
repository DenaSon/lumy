<?php

namespace Tests\Feature;

use App\Integrations\Zernio\InstagramSyncFreshness;
use App\Integrations\Zernio\InstagramSyncService;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class InstagramSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('zernio.account_id', 'account_command');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_full_sync_remains_the_default_when_only_is_not_supplied(): void
    {
        $this->mock(InstagramSyncService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sync')
                ->once()
                ->with('2026-09-01', '2026-09-07')
                ->andReturn($this->fullSummary());
        });

        $this->artisan('lumy:sync-instagram', [
            '--from' => '2026-09-01',
            '--to' => '2026-09-07',
        ])->assertExitCode(0);
    }

    public function test_content_analytics_stage_runs_without_triggering_full_sync(): void
    {
        $account = $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldNotReceive('sync');
            $mock->shouldReceive('syncContentAnalytics')
                ->once()
                ->withArgs(fn (SocialAccount $received, string $from, string $to) => $received->is($account)
                    && $from === '2026-09-01'
                    && $to === '2026-09-07')
                ->andReturn($this->syncResult(discovered: 10, created: 3, updated: 7));
        });

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'content-analytics',
            '--from' => '2026-09-01',
            '--to' => '2026-09-07',
        ])->assertExitCode(0);

        $this->assertNotNull($account->fresh()->last_synced_at);
    }

    public function test_invalid_stage_and_invalid_stage_date_usage_fail_before_syncing(): void
    {
        $this->mock(InstagramSyncService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sync');
            $mock->shouldNotReceive('syncContents');
        });

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'unknown-stage',
        ])->assertExitCode(1);

        $this->account();

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'contents',
            '--from' => '2026-09-01',
        ])->assertExitCode(1);
    }

    public function test_account_insights_rejects_ranges_longer_than_provider_limit(): void
    {
        $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('syncAccountInsights');
        });

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'account-insights',
            '--from' => '2026-01-01',
            '--to' => '2026-09-07',
        ])->assertExitCode(1);
    }

    public function test_followers_use_provider_safe_default_range_and_reject_longer_ranges(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC'));
        $account = $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldReceive('syncFollowerHistory')
                ->once()
                ->withArgs(fn (SocialAccount $received, string $from, string $to) => $received->is($account)
                    && $from === '2026-06-11'
                    && $to === '2026-09-07')
                ->andReturn($this->syncResult(discovered: 1, created: 1));
        });

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'followers',
        ])->assertExitCode(0);

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'followers',
            '--from' => '2026-06-10',
            '--to' => '2026-09-07',
        ])->assertExitCode(1);
    }

    public function test_partial_stage_result_returns_non_zero_exit_code(): void
    {
        $account = $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldReceive('syncDemographics')
                ->once()
                ->withArgs(fn (SocialAccount $received) => $received->is($account))
                ->andReturn([
                    ...$this->syncResult(discovered: 3, created: 2),
                    'failed_count' => 1,
                    'errors' => ['One demographic row failed.'],
                ]);
        });

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'demographics',
        ])->assertExitCode(1);
    }

    public function test_incremental_sync_refreshes_metadata_and_recent_analytics_only(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC'));
        $account = $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldNotReceive('sync');
            $mock->shouldReceive('syncContents')
                ->once()
                ->withArgs(fn (SocialAccount $received) => $received->is($account))
                ->andReturn($this->syncResult(discovered: 111, updated: 111));
            $mock->shouldReceive('syncContentAnalytics')
                ->once()
                ->withArgs(fn (SocialAccount $received, string $from, string $to) => $received->is($account)
                    && $from === '2026-08-08'
                    && $to === '2026-09-07')
                ->andReturn($this->syncResult(discovered: 20, created: 2, updated: 18));
        });

        $this->artisan('lumy:sync-instagram-incremental')
            ->assertExitCode(0);

        $this->assertNotNull($account->fresh()->last_synced_at);
    }

    public function test_incremental_sync_validates_rolling_window(): void
    {
        $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('syncContents');
            $mock->shouldNotReceive('syncContentAnalytics');
        });

        $this->artisan('lumy:sync-instagram-incremental', ['--days' => 0])
            ->assertExitCode(1);

        $this->artisan('lumy:sync-instagram-incremental', ['--days' => 91])
            ->assertExitCode(1);
    }

    public function test_freshness_check_refreshes_only_stale_stages(): void
    {
        $now = CarbonImmutable::parse('2026-09-07 12:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);
        $account = $this->account();

        $this->completedRun($account, 'contents', $now->subHours(3));
        $this->completedRun($account, 'content_analytics', $now->subHours(3));
        $this->completedRun($account, 'account_analytics', $now->subHours(23));
        $this->completedRun($account, 'demographics', $now->subHours(23));
        $this->completedRun($account, 'followers', $now->subHours(23));

        $status = app(InstagramSyncFreshness::class)->status($account, $now);
        $this->assertTrue($status['incremental']['due']);
        $this->assertFalse($status['account-insights']['due']);
        $this->assertFalse($status['demographics']['due']);
        $this->assertFalse($status['followers']['due']);

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldReceive('syncContents')
                ->once()
                ->withArgs(fn (SocialAccount $received) => $received->is($account))
                ->andReturn($this->syncResult(discovered: 111, updated: 111));
            $mock->shouldReceive('syncContentAnalytics')
                ->once()
                ->withArgs(fn (SocialAccount $received, string $from, string $to) => $received->is($account)
                    && $from === '2026-08-08'
                    && $to === '2026-09-07')
                ->andReturn($this->syncResult(discovered: 20, updated: 20));
            $mock->shouldNotReceive('syncAccountInsights');
            $mock->shouldNotReceive('syncDemographics');
            $mock->shouldNotReceive('syncFollowerHistory');
        });

        $this->artisan('lumy:sync-instagram-fresh')
            ->assertExitCode(0);
    }

    public function test_soft_freshness_check_does_not_break_local_runtime_before_bootstrap(): void
    {
        $this->artisan('lumy:sync-instagram-fresh', ['--soft' => true])
            ->assertExitCode(0);
    }

    public function test_local_scheduler_registers_freshness_watch(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('lumy:sync-instagram-fresh')
            ->assertExitCode(0);
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_command',
        ]);
    }

    private function completedRun(SocialAccount $account, string $syncType, CarbonImmutable $finishedAt): void
    {
        $account->syncRuns()->create([
            'provider' => 'zernio',
            'sync_type' => $syncType,
            'status' => 'completed',
            'started_at' => $finishedAt->subMinute(),
            'finished_at' => $finishedAt,
        ]);
    }

    private function fullSummary(): array
    {
        return [
            'social_account_id' => 1,
            'provider_account_id' => 'account_command',
            'contents' => $this->syncResult(),
            'content_analytics' => $this->syncResult(),
            'account_analytics' => $this->syncResult(),
            'demographics' => $this->syncResult(),
            'followers' => $this->syncResult(),
        ];
    }

    private function syncResult(int $discovered = 0, int $created = 0, int $updated = 0): array
    {
        return [
            'discovered_count' => $discovered,
            'created_count' => $created,
            'updated_count' => $updated,
            'failed_count' => 0,
            'errors' => [],
            'provider_payload' => null,
        ];
    }
}
