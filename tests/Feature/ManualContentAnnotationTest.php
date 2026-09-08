<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManualContentAnnotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_annotation_page_can_be_rendered_for_content(): void
    {
        $content = $this->content($this->account(), 'Content ready for annotation', '2026-09-07T08:00:00Z');

        $this->get(route('content.annotate', $content))
            ->assertOk()
            ->assertSee('Annotation محتوا')
            ->assertSee('Content ready for annotation')
            ->assertSee('Hooks')
            ->assertSee('تغییرات ذخیره‌نشده');
    }

    public function test_manual_annotation_saves_dna_topics_and_multiple_hooks(): void
    {
        $content = $this->content($this->account(), 'Teach WireGuard', '2026-09-07T08:00:00Z');
        $pillar = ContentPillar::create([
            'name' => 'Networking',
            'slug' => 'networking',
            'is_active' => true,
        ]);
        $wireGuard = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'WireGuard',
            'slug' => 'wireguard',
            'is_active' => true,
        ]);
        $vpn = Topic::create([
            'content_pillar_id' => $pillar->id,
            'name' => 'VPN',
            'slug' => 'vpn',
            'is_active' => true,
        ]);

        Livewire::test('pages::panel.content.annotate', ['content' => $content])
            ->set('primaryPillarId', $pillar->id)
            ->assertSet('hasUnsavedChanges', true)
            ->set('goal', 'education')
            ->set('ctaType', 'save')
            ->set('targetAudience', 'Linux users')
            ->set('productionStyle', 'screen recording')
            ->set('coverStyle', 'terminal screenshot')
            ->set('notes', 'Evergreen practical tutorial')
            ->set('selectedTopicIds', [$wireGuard->id, $vpn->id])
            ->set('primaryTopicId', $wireGuard->id)
            ->set('hooks', [
                [
                    'id' => null,
                    'text' => 'VPN را در چند دقیقه راه بینداز',
                    'type' => 'how_to',
                    'source' => 'video_overlay',
                    'notes' => 'Opening overlay',
                ],
                [
                    'id' => null,
                    'text' => 'WireGuard چرا این‌قدر سریع است؟',
                    'type' => 'question',
                    'source' => 'spoken',
                    'notes' => '',
                ],
            ])
            ->set('primaryHookIndex', 1)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('hasUnsavedChanges', false)
            ->assertSet('savedMessage', 'Annotation ذخیره شد.');

        $annotation = $content->fresh()->annotation;

        $this->assertNotNull($annotation);
        $this->assertSame($pillar->id, $annotation->primary_pillar_id);
        $this->assertSame('education', $annotation->goal);
        $this->assertSame('save', $annotation->cta_type);
        $this->assertSame('Linux users', $annotation->target_audience);
        $this->assertSame('screen recording', $annotation->production_style);
        $this->assertSame('terminal screenshot', $annotation->cover_style);
        $this->assertSame('Evergreen practical tutorial', $annotation->notes);
        $this->assertNotNull($annotation->annotated_at);

        $topics = $content->fresh()->topics()->orderBy('topics.id')->get();
        $this->assertCount(2, $topics);
        $this->assertTrue((bool) $topics->firstWhere('id', $wireGuard->id)->pivot->is_primary);
        $this->assertFalse((bool) $topics->firstWhere('id', $vpn->id)->pivot->is_primary);

        $hooks = $content->fresh()->hooks()->orderBy('position')->get();
        $this->assertCount(2, $hooks);
        $this->assertSame('VPN را در چند دقیقه راه بینداز', $hooks[0]->text);
        $this->assertFalse($hooks[0]->is_primary);
        $this->assertSame('WireGuard چرا این‌قدر سریع است؟', $hooks[1]->text);
        $this->assertTrue($hooks[1]->is_primary);
        $this->assertSame('spoken', $hooks[1]->source);
    }

    public function test_editing_annotation_updates_existing_hook_and_removes_deleted_hook(): void
    {
        $content = $this->content($this->account(), 'Existing annotation', '2026-09-07T08:00:00Z');
        $keep = $content->hooks()->create([
            'text' => 'Old hook text',
            'type' => 'statement',
            'source' => 'cover',
            'position' => 0,
            'is_primary' => true,
        ]);
        $remove = $content->hooks()->create([
            'text' => 'Remove me',
            'type' => 'question',
            'source' => 'caption',
            'position' => 1,
            'is_primary' => false,
        ]);

        Livewire::test('pages::panel.content.annotate', ['content' => $content])
            ->set('hooks', [
                [
                    'id' => $keep->id,
                    'text' => 'Updated hook text',
                    'type' => 'result',
                    'source' => 'cover',
                    'notes' => 'Updated note',
                ],
            ])
            ->set('primaryHookIndex', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('content_hooks', [
            'id' => $keep->id,
            'content_id' => $content->id,
            'text' => 'Updated hook text',
            'type' => 'result',
            'source' => 'cover',
            'position' => 0,
            'is_primary' => 1,
        ]);
        $this->assertDatabaseMissing('content_hooks', ['id' => $remove->id]);
    }

    public function test_save_and_next_moves_to_the_next_older_content(): void
    {
        $account = $this->account();
        $older = $this->content($account, 'Older content', '2026-09-06T08:00:00Z');
        $newer = $this->content($account, 'Newer content', '2026-09-07T08:00:00Z');

        Livewire::test('pages::panel.content.annotate', ['content' => $newer])
            ->call('saveAndNext')
            ->assertHasNoErrors()
            ->assertRedirect(route('content.annotate', $older));

        $this->assertDatabaseHas('content_annotations', [
            'content_id' => $newer->id,
        ]);
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'account_123',
        ]);
    }

    private function content(SocialAccount $account, string $caption, string $publishedAt): Content
    {
        static $sequence = 0;
        $sequence++;

        return $account->contents()->create([
            'platform_post_id' => 'annotation_post_'.$sequence,
            'permalink' => 'https://www.instagram.com/p/annotation'.$sequence.'/',
            'caption' => $caption,
            'content_type' => 'reel',
            'analytics_status' => 'available',
            'published_at' => $publishedAt,
        ]);
    }
}
