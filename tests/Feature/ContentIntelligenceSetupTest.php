<?php

namespace Tests\Feature;

use App\ContentIntelligence\AnnotationCoverage;
use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentIntelligenceSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_annotation_coverage_counts_only_observed_content_dna(): void
    {
        $account = $this->account();
        $full = $this->content($account, 'Full annotation');
        $partial = $this->content($account, 'Partial annotation');
        $this->content($account, 'No annotation');

        $this->snapshot($full);
        $this->snapshot($partial);

        $pillar = ContentPillar::create([
            'name' => 'Linux',
            'slug' => 'linux',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $topic = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Permissions',
            'slug' => 'permissions',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $full->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
            'annotated_at' => now(),
        ]);
        $full->topics()->attach($topic->id, ['is_primary' => true]);
        $full->hooks()->create([
            'text' => 'Linux permissionها را درست بفهم',
            'type' => 'how_to',
            'source' => 'video_overlay',
            'position' => 0,
            'is_primary' => true,
        ]);

        $partial->annotation()->create([
            'goal' => 'growth',
            'annotated_at' => now(),
        ]);
        $partial->hooks()->create([
            'text' => 'Partial hook',
            'type' => 'curiosity',
            'source' => null,
            'position' => 0,
            'is_primary' => false,
        ]);

        $coverage = app(AnnotationCoverage::class)->summary();

        $this->assertSame(3, $coverage['total']);
        $this->assertSame(2, $coverage['with_metrics']);
        $this->assertSame(2, $coverage['annotated']);
        $this->assertSame(1, $coverage['with_primary_pillar']);
        $this->assertSame(1, $coverage['with_primary_topic']);
        $this->assertSame(1, $coverage['with_primary_hook']);
        $this->assertSame(2, $coverage['with_hook_type']);
        $this->assertSame(1, $coverage['with_hook_source']);
        $this->assertSame(2, $coverage['with_goal']);
        $this->assertEqualsWithDelta(2 / 3, $coverage['annotation_rate'], 0.000001);
        $this->assertEqualsWithDelta(1 / 3, $coverage['primary_hook_rate'], 0.000001);
    }

    public function test_taxonomy_workspace_creates_edits_orders_and_deactivates_taxonomy(): void
    {
        $this->get(route('intelligence.index'))
            ->assertOk()
            ->assertSee('Taxonomy و کیفیت Annotation')
            ->assertSee('Annotation Coverage')
            ->assertSee('wire:sort="reorderTopic"', false);

        Livewire::test('pages::panel.intelligence.index')
            ->set('pillarName', 'DevOps')
            ->set('pillarSlug', 'devops')
            ->set('pillarDescription', 'Operational engineering')
            ->set('pillarSortOrder', 20)
            ->call('savePillar')
            ->assertHasNoErrors()
            ->assertSet('savedMessage', 'Pillar ساخته شد.');

        $pillar = ContentPillar::query()->where('slug', 'devops')->firstOrFail();

        Livewire::test('pages::panel.intelligence.index')
            ->set('topicPillarId', $pillar->id)
            ->set('topicName', 'Monitoring')
            ->set('topicSlug', 'monitoring')
            ->set('topicSortOrder', 20)
            ->call('saveTopic')
            ->assertHasNoErrors();

        Livewire::test('pages::panel.intelligence.index')
            ->set('topicPillarId', $pillar->id)
            ->set('topicName', 'Docker')
            ->set('topicSlug', 'docker')
            ->set('topicSortOrder', 10)
            ->call('saveTopic')
            ->assertHasNoErrors();

        $monitoring = Topic::query()->where('slug', 'monitoring')->firstOrFail();
        $docker = Topic::query()->where('slug', 'docker')->firstOrFail();

        $this->assertSame(['Docker', 'Monitoring'], $pillar->fresh()->topics->pluck('name')->all());

        Livewire::test('pages::panel.intelligence.index')
            ->call('editTopic', $monitoring->id)
            ->set('topicName', 'Observability')
            ->set('topicSlug', 'observability')
            ->set('topicSortOrder', 5)
            ->call('saveTopic')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('topics', [
            'id' => $monitoring->id,
            'name' => 'Observability',
            'slug' => 'observability',
            'sort_order' => 5,
        ]);

        Livewire::test('pages::panel.intelligence.index')
            ->call('toggleTopic', $docker->id)
            ->call('togglePillar', $pillar->id);

        $this->assertFalse($docker->fresh()->is_active);
        $this->assertFalse($pillar->fresh()->is_active);
        $this->assertDatabaseHas('topics', ['id' => $docker->id]);
        $this->assertDatabaseHas('content_pillars', ['id' => $pillar->id]);
    }

    public function test_topics_can_be_reordered_with_native_sort_handler(): void
    {
        $pillar = ContentPillar::create([
            'name' => 'DevOps',
            'slug' => 'devops-sort',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $first = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Docker',
            'slug' => 'docker-sort',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $second = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Monitoring',
            'slug' => 'monitoring-sort',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $third = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'Networking',
            'slug' => 'networking-sort',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        Livewire::test('pages::panel.intelligence.index')
            ->call('reorderTopic', $third->id, 0)
            ->assertSet('savedMessage', 'ترتیب Topicها ذخیره شد.');

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            $pillar->fresh()->topics->pluck('id')->all(),
        );
        $this->assertSame([0, 1, 2], $pillar->fresh()->topics->pluck('sort_order')->all());
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_taxonomy',
        ]);
    }

    private function content(SocialAccount $account, string $caption): Content
    {
        static $sequence = 0;
        $sequence++;

        return $account->contents()->create([
            'platform_post_id' => 'taxonomy_post_'.$sequence,
            'caption' => $caption,
            'content_type' => 'reel',
            'analytics_status' => 'available',
            'published_at' => now(),
        ]);
    }

    private function snapshot(Content $content): void
    {
        $content->metricSnapshots()->create([
            'captured_at' => now(),
            'provider_updated_at' => now(),
            'snapshot_type' => 'initial',
            'snapshot_window' => 'lifetime',
            'views' => 100,
        ]);
    }
}
