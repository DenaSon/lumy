<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionContextExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_export_contains_decision_context_and_only_eligible_evidence(): void
    {
        $account = $this->account();
        config(['zernio.account_id' => $account->provider_account_id]);

        [$pillar, $topic] = $this->taxonomy();

        foreach (range(1, 5) as $index) {
            $content = $this->annotatedContent($account, $pillar, $topic, 'question', $index);
            $this->snapshot($content, 100 + $index, 10, 10, 5000, 40);
        }

        foreach (range(1, 5) as $index) {
            $content = $this->annotatedContent($account, $pillar, $topic, 'result', 10 + $index);
            $this->snapshot($content, 80 + $index, 5, 5, 4000, 55);
        }

        $account->accountMetricSnapshots()->create([
            'period_start' => '2026-08-10T00:00:00Z',
            'period_end' => '2026-09-07T00:00:00Z',
            'captured_at' => '2026-09-07T12:00:00Z',
            'followers_count' => 11662,
            'reach' => 465531,
            'views' => 1417418,
            'accounts_engaged' => 55674,
            'total_interactions' => 162212,
        ]);

        $response = $this->get(route('decision-context.export', ['format' => 'json']));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        $this->assertStringContainsString('attachment; filename="lumy-decision-context-lumixo.dev-', (string) $response->headers->get('Content-Disposition'));

        $context = $response->json();

        $this->assertSame('lumy.decision_context.v1', $context['meta']['schema']);
        $this->assertSame('lumixo.dev', $context['account']['username']);
        $this->assertSame(10, $context['account']['content_count']);
        $this->assertSame(11662, $context['account']['followers_count']);
        $this->assertSame('format', $context['performance_baseline']['basis_dimension']);
        $this->assertArrayHasKey('high_intent_rate', $context['performance_baseline']['metrics']);
        $this->assertArrayHasKey('by_views', $context['top_content']);
        $this->assertArrayHasKey('by_high_intent', $context['top_content']);
        $this->assertSame('question', $context['top_content']['by_high_intent'][0]['content_dna']['primary_hook']['type']);

        $evidence = collect($context['intelligence']['primary_hook_type']['evidence']);

        $this->assertNotEmpty($evidence);
        $this->assertTrue($evidence->every(fn (array $item) => in_array($item['evidence_status'], ['usable', 'exploratory'], true)));
        $this->assertTrue($evidence->every(fn (array $item) => in_array($item['direction'], ['above', 'below'], true)));
        $this->assertTrue($evidence->contains(fn (array $item) => $item['evidence_id'] === 'primary_hook_type:question:high_intent_rate'));
        $this->assertFalse($context['data_quality']['statistical_significance_tested']);
        $this->assertFalse($context['data_quality']['causal_inference_performed']);
    }

    public function test_markdown_export_is_ai_ready_and_contains_guardrails(): void
    {
        $account = $this->account();
        config(['zernio.account_id' => $account->provider_account_id]);

        [$pillar, $topic] = $this->taxonomy();

        foreach (range(1, 5) as $index) {
            $content = $this->annotatedContent($account, $pillar, $topic, 'question', $index);
            $this->snapshot($content, 100, 10, 10, 5000, 40);
        }

        foreach (range(1, 5) as $index) {
            $content = $this->annotatedContent($account, $pillar, $topic, 'result', 10 + $index);
            $this->snapshot($content, 100, 5, 5, 4000, 55);
        }

        $response = $this->get(route('decision-context.export', ['format' => 'markdown']));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        $body = $response->getContent();

        $this->assertStringContainsString('# Lumy Decision Context', $body);
        $this->assertStringContainsString('## Intelligence Evidence', $body);
        $this->assertStringContainsString('primary_hook_type:question:high_intent_rate', $body);
        $this->assertStringContainsString('## Interpretation Guardrails', $body);
        $this->assertStringContainsString('No statistical significance testing has been performed.', $body);
        $this->assertStringContainsString('cite one or more `evidence_id` values', $body);
    }

    public function test_export_returns_not_found_without_an_instagram_account(): void
    {
        $this->get(route('decision-context.export', ['format' => 'json']))
            ->assertNotFound();
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'decision_context_account',
        ]);
    }

    /** @return array{ContentPillar, Topic} */
    private function taxonomy(): array
    {
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

        return [$pillar, $topic];
    }

    private function annotatedContent(
        SocialAccount $account,
        ContentPillar $pillar,
        Topic $topic,
        string $hookType,
        int $sequence,
    ): Content {
        $content = $account->contents()->create([
            'platform_post_id' => 'decision_context_'.$sequence,
            'permalink' => 'https://www.instagram.com/p/decision'.$sequence.'/',
            'caption' => ucfirst($hookType).' decision fixture '.$sequence,
            'content_type' => 'reel',
            'analytics_status' => 'available',
            'published_at' => '2026-09-07T08:00:00Z',
        ]);

        $content->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
            'cta_type' => 'save',
            'target_audience' => 'developers',
            'production_style' => 'screen',
            'cover_style' => 'minimal',
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
        int $shares,
        int $saves,
        int $avgWatchTimeMs,
        float $skipRate,
    ): void {
        $content->metricSnapshots()->create([
            'captured_at' => now(),
            'provider_updated_at' => now(),
            'snapshot_type' => 'scheduled',
            'snapshot_window' => 'lifetime',
            'views' => $views,
            'reach' => $views,
            'likes' => 10,
            'comments' => 5,
            'shares' => $shares,
            'saves' => $saves,
            'avg_watch_time_ms' => $avgWatchTimeMs,
            'video_duration_seconds' => 10,
            'skip_rate' => $skipRate,
        ]);
    }
}
