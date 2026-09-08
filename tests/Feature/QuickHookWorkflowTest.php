<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickHookWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_hook_page_renders_queue_and_bulk_imports_hooks(): void
    {
        $account = $this->account();
        config(['zernio.account_id' => $account->provider_account_id]);
        $content = $this->content($account, '2026-09-08T10:00:00Z');

        $this->get(route('content.hooks'))
            ->assertOk()
            ->assertSee('ثبت سریع Hook')
            ->assertSee('Hook Coverage')
            ->assertSee($content->caption);

        Livewire::test('pages::panel.content.hooks')
            ->set('bulkHooks', "Hook one\nHook two\nHook three")
            ->call('importBulkHooks')
            ->assertSet('primaryHookIndex', 0)
            ->assertSet('hooks', fn (array $hooks) => count($hooks) === 3
                && $hooks[0]['text'] === 'Hook one'
                && $hooks[2]['text'] === 'Hook three');
    }

    public function test_save_and_next_persists_primary_hook_without_touching_existing_annotation(): void
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

        $newer = $this->content($account, '2026-09-08T10:00:00Z');
        $older = $this->content($account, '2026-09-07T10:00:00Z');
        $newer->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
            'cta_type' => 'save',
            'annotated_at' => now(),
        ]);
        $newer->topics()->attach($topic->id, ['is_primary' => true]);

        Livewire::test('pages::panel.content.hooks')
            ->assertSet('contentId', $newer->id)
            ->set('hooks.0.text', '۵ دستور لینوکس که باید بلد باشی')
            ->call('setHookType', 0, 'list')
            ->call('setHookSource', 0, 'video_overlay')
            ->call('saveAndNext')
            ->assertSet('contentId', $older->id);

        $hook = $newer->hooks()->first();

        $this->assertNotNull($hook);
        $this->assertTrue((bool) $hook->is_primary);
        $this->assertSame('list', $hook->type);
        $this->assertSame('video_overlay', $hook->source);
        $this->assertSame('education', $newer->fresh()->annotation?->goal);
        $this->assertSame('save', $newer->fresh()->annotation?->cta_type);
        $this->assertTrue($newer->fresh()->topics()->whereKey($topic->id)->exists());
    }

    public function test_existing_primary_hook_is_not_offered_in_missing_queue(): void
    {
        $account = $this->account();
        config(['zernio.account_id' => $account->provider_account_id]);

        $done = $this->content($account, '2026-09-08T10:00:00Z');
        $missing = $this->content($account, '2026-09-07T10:00:00Z');
        $done->hooks()->create([
            'text' => 'Existing hook',
            'type' => 'question',
            'source' => 'cover',
            'position' => 0,
            'is_primary' => true,
        ]);

        Livewire::test('pages::panel.content.hooks')
            ->assertSet('contentId', $missing->id)
            ->assertSet('queueSummary.completed', 1)
            ->assertSet('queueSummary.remaining', 1);
    }

    public function test_quick_hook_page_has_honest_empty_state_without_account(): void
    {
        $this->get(route('content.hooks'))
            ->assertOk()
            ->assertSee('اکانت Instagram پیدا نشد');
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
            'provider_account_id' => 'quick_hook_account',
        ]);
    }

    private function content(SocialAccount $account, string $publishedAt): Content
    {
        static $sequence = 0;
        $sequence++;

        return $account->contents()->create([
            'platform_post_id' => 'quick_hook_'.$sequence,
            'caption' => 'Quick hook fixture '.$sequence,
            'content_type' => 'reel',
            'analytics_status' => 'available',
            'published_at' => $publishedAt,
        ]);
    }
}
