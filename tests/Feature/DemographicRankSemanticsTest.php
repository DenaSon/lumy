<?php

namespace Tests\Feature;

use App\Integrations\Zernio\InstagramSyncService;
use App\Models\DemographicSnapshot;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemographicRankSemanticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('zernio.base_url', 'https://zernio.com/api/v1');
        config()->set('zernio.api_key', 'test-api-key');
    }

    public function test_provider_array_order_is_not_persisted_as_rank(): void
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_123',
        ]);

        Http::fake([
            'https://zernio.com/api/v1/analytics/instagram/demographics*' => Http::response([
                'success' => true,
                'accountId' => 'account_123',
                'metric' => 'follower_demographics',
                'timeframe' => 'this_month',
                'demographics' => [
                    'city' => [
                        ['dimension' => 'Bandar-e Anzali', 'value' => 131],
                        ['dimension' => 'Tabriz', 'value' => 2243],
                        ['dimension' => 'Tehran', 'value' => 1700, 'rank' => 7],
                    ],
                ],
            ]),
        ]);

        $result = app(InstagramSyncService::class)->syncDemographics($account);

        $rows = DemographicSnapshot::query()
            ->where('dimension_type', 'city')
            ->orderBy('provider_position')
            ->get();

        $this->assertSame(3, $result['created_count']);
        $this->assertSame(0, $result['failed_count']);
        $this->assertSame([1, 2, 3], $rows->pluck('provider_position')->all());
        $this->assertSame([null, null, 7], $rows->pluck('rank')->all());
        $this->assertSame(
            ['Tabriz', 'Tehran', 'Bandar-e Anzali'],
            DemographicSnapshot::query()
                ->where('dimension_type', 'city')
                ->highestValueFirst()
                ->pluck('dimension')
                ->all(),
        );
    }

    public function test_cleanup_migration_can_resume_after_provider_position_column_already_exists(): void
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_resume_rank_cleanup',
        ]);

        $snapshot = $account->demographicSnapshots()->create([
            'captured_at' => '2026-09-07T13:30:00Z',
            'dimension_type' => 'city',
            'dimension' => 'Tabriz',
            'value' => 2243,
            'rank' => 2,
            'provider_position' => null,
            'is_partial' => true,
        ]);

        $migration = require database_path('migrations/2026_09_07_181000_cleanup_demographic_rank_semantics.php');
        $migration->up();

        $snapshot->refresh();

        $this->assertNull($snapshot->rank);
        $this->assertSame(2, $snapshot->provider_position);
    }
}
