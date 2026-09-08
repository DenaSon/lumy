<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentIntelligenceUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_workspace_defers_core_and_evidence_without_changing_final_content(): void
    {
        $account = $this->account();
        config(['zernio.account_id' => $account->provider_account_id]);

        $pillar = ContentPillar::create([
            'name' => 'Linux',
            'slug' => 'linux',
            'is_active' => true,
        ]);
        $topic = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Commands',
            'slug' => 'commands',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $index) {
            $content = $this->annotatedContent($account, $pillar, $topic, 'question');
            $this->snapshot($content, 100, 10, 10, 10, 10, 5000, 10000, 40);
        }

        foreach (range(1, 5) as $index) {
            $content = $this->annotatedContent($account, $pillar, $topic, 'result');
            $this->snapshot($content, 100, 10, 5, 5, 5, 4000, 10000, 55);
        }

        $this->get(route('intelligence.analysis'))
            ->assertOk()
            ->assertSee('تحلیل الگوهای محتوا')
            ->assertSee('Quick Hook')
            ->assertSee('Format')
            ->assertSee('در حال محاسبه Baseline و گروه‌ها...')
            ->assertSee('در حال آماده‌سازی Evidence...')
            ->assertDontSee('question');

        $component = Livewire::test('pages::panel.intelligence.analysis');
        $component->instance()->renderIsland('analysis-core', mount: true);
        $component->instance()->renderIsland('analysis-evidence', mount: true);
        $loaded = implode("\n", $component->instance()->getRenderedIslandFragments());

        $this->assertStringContainsString('Overall baseline', $loaded);
        $this->assertStringContainsString('Group vs peer baseline', $loaded);
        $this->assertStringContainsString('Evidence candidates', $loaded);
        $this->assertStringContainsString('Eligible Evidence', $loaded);
        $this->assertStringContainsString('Usable Evidence', $loaded);
        $this->assertStringContainsString('question', $loaded);
        $this->assertStringContainsString('result', $loaded);
        $this->assertStringContainsString('Usable', $loaded);
        $this->assertStringContainsString('Relative lift', $loaded);
        $this->assertStringContainsString('wire:transition="analysis-baseline"', $loaded);
        $this->assertStringContainsString('wire:transition="analysis-evidence"', $loaded);

        $component->assertSee('چطور این صفحه را بخوانیم؟');
    }

    public function test_dimension_and_evidence_filters_validate_their_url_state(): void
    {
        $account = $this->account();
        config(['zernio.account_id' => $account->provider_account_id]);

        Livewire::test('pages::panel.intelligence.analysis')
            ->set('dimension', 'format')
            ->assertSet('dimension', 'format')
            ->set('dimension', 'goal')
            ->assertSet('dimension', 'goal')
            ->set('dimension', 'not-a-dimension')
            ->assertSet('dimension', 'primary_hook_type')
            ->set('evidenceFilter', 'eligible')
            ->assertSet('evidenceFilter', 'eligible')
            ->set('evidenceFilter', 'usable')
            ->assertSet('evidenceFilter', 'usable')
            ->set('evidenceFilter', 'not-a-filter')
            ->assertSet('evidenceFilter', 'all');
    }

    public function test_analysis_workspace_has_an_honest_empty_state_without_an_account(): void
    {
        $this->get(route('intelligence.analysis'))
            ->assertOk()
            ->assertSee('اکانت Instagram پیدا نشد');
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_intelligence_ui',
        ]);
    }

    private function annotatedContent(
        SocialAccount $account,
        ContentPillar $pillar,
        Topic $topic,
        string $hookType,
    ): Content {
        static $sequence = 0;
        $sequence++;

        $content = $account->contents()->create([
            'platform_post_id' => 'intelligence_ui_'.$sequence,
            'caption' => 'UI fixture '.$sequence,
            'content_type' => 'reel',
            'analytics_status' => 'available',
            'published_at' => '2026-09-07T08:00:00Z',
        ]);

        $content->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
            'cta_type' => 'save',
            'production_style' => 'screen',
            'annotated_at' => now(),
        ]);
        $content->topics()->attach($topic->id, ['is_primary' => true]);
        $content->hooks()->create([
            'text' => 'Primary '.$hookType,
            'type' => $hookType,
            'source' => 'video_overlay',
            'position' => 0,
            'is_primary' => true,
        ]);

        return $content;
    }

    private function snapshot(
        Content $content,
        int $views,
        int $likes,
        int $comments,
        int $shares,
        int $saves,
        int $avgWatchTimeMs,
        int $durationMs,
        float $skipRate,
    ): void {
        $content->metricSnapshots()->create([
            'captured_at' => now(),
            'provider_updated_at' => now(),
            'snapshot_type' => 'scheduled',
            'snapshot_window' => 'lifetime',
            'views' => $views,
            'reach' => $views,
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'saves' => $saves,
            'avg_watch_time_ms' => $avgWatchTimeMs,
            'video_duration_seconds' => $durationMs / 1000,
            'skip_rate' => $skipRate,
        ]);
    }
}
