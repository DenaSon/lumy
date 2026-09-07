<?php

namespace Tests\Feature;

use App\Integrations\Zernio\InstagramSyncService;
use App\Models\SocialAccount;
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
                ->andReturn($this->result(discovered: 10, created: 3, updated: 7));
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

    public function test_partial_stage_result_returns_non_zero_exit_code(): void
    {
        $account = $this->account();

        $this->mock(InstagramSyncService::class, function (MockInterface $mock) use ($account) {
            $mock->shouldReceive('syncDemographics')
                ->once()
                ->withArgs(fn (SocialAccount $received) => $received->is($account))
                ->andReturn([
                    ...$this->result(discovered: 3, created: 2),
                    'failed_count' => 1,
                    'errors' => ['One demographic row failed.'],
                ]);
        });

        $this->artisan('lumy:sync-instagram', [
            '--only' => 'demographics',
        ])->assertExitCode(1);
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

    private function fullSummary(): array
    {
        return [
            'social_account_id' => 1,
            'provider_account_id' => 'account_command',
            'contents' => $this->result(),
            'content_analytics' => $this->result(),
            'account_analytics' => $this->result(),
            'demographics' => $this->result(),
            'followers' => $this->result(),
        ];
    }

    private function result(int $discovered = 0, int $created = 0, int $updated = 0): array
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
