<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentPillar;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentIntelligenceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_supports_multiple_manual_hooks(): void
    {
        $content = $this->createContent();

        $content->hooks()->create([
            'text' => 'چطور بدون خرید VPN سرور شخصی بسازیم؟',
            'type' => 'how_to',
            'source' => 'video_overlay',
            'position' => 1,
            'is_primary' => true,
        ]);

        $content->hooks()->create([
            'text' => 'این روش روی لینوکس چند دقیقه بیشتر زمان نمی‌برد.',
            'type' => 'curiosity',
            'source' => 'spoken',
            'position' => 2,
            'is_primary' => false,
        ]);

        $content->refresh();

        $this->assertCount(2, $content->hooks);
        $this->assertTrue($content->hooks->first()->is_primary);
        $this->assertSame('video_overlay', $content->hooks->first()->source);
    }

    public function test_content_can_be_classified_with_pillars_topics_and_annotation(): void
    {
        $content = $this->createContent();

        $pillar = ContentPillar::create([
            'name' => 'Security',
            'slug' => 'security',
        ]);

        $wireGuard = $pillar->topics()->create([
            'name' => 'WireGuard',
            'slug' => 'wireguard',
        ]);

        $vpn = $pillar->topics()->create([
            'name' => 'VPN',
            'slug' => 'vpn',
        ]);

        $content->topics()->attach($wireGuard->id, ['is_primary' => true]);
        $content->topics()->attach($vpn->id, ['is_primary' => false]);

        $content->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
            'cta_type' => 'save',
            'target_audience' => 'Linux users',
            'production_style' => 'screen_demo',
            'annotated_at' => now(),
        ]);

        $content->refresh();

        $this->assertCount(2, $content->topics);
        $this->assertTrue((bool) $content->topics->firstWhere('id', $wireGuard->id)->pivot->is_primary);
        $this->assertSame('education', $content->annotation->goal);
        $this->assertSame('Security', $content->annotation->primaryPillar->name);
        $this->assertNotNull($content->annotation->annotated_at);
    }

    public function test_deleting_content_cascades_manual_intelligence_records(): void
    {
        $content = $this->createContent();

        $pillar = ContentPillar::create([
            'name' => 'Linux',
            'slug' => 'linux',
        ]);

        $topic = $pillar->topics()->create([
            'name' => 'Networking',
            'slug' => 'networking',
        ]);

        $hook = $content->hooks()->create([
            'text' => 'یک تنظیم کوچک که شبکه لینوکس را بهتر می‌کند',
            'type' => 'curiosity',
            'source' => 'cover',
            'is_primary' => true,
        ]);

        $annotation = $content->annotation()->create([
            'primary_pillar_id' => $pillar->id,
            'goal' => 'education',
        ]);

        $content->topics()->attach($topic->id, ['is_primary' => true]);

        $content->delete();

        $this->assertDatabaseMissing('content_hooks', ['id' => $hook->id]);
        $this->assertDatabaseMissing('content_annotations', ['id' => $annotation->id]);
        $this->assertDatabaseMissing('content_topic', [
            'content_id' => $content->id,
            'topic_id' => $topic->id,
        ]);
        $this->assertDatabaseHas('topics', ['id' => $topic->id]);
    }

    private function createContent(): Content
    {
        $account = SocialAccount::create([
            'platform' => 'instagram',
            'provider' => 'zernio',
            'username' => 'lumixo.dev',
        ]);

        return $account->contents()->create([
            'platform_post_id' => 'test-post-'.uniqid(),
            'content_type' => 'reel',
            'analytics_status' => 'available',
        ]);
    }
}
