<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_catalog_can_be_rendered_when_empty(): void
    {
        $this->get(route('content.index'))
            ->assertOk()
            ->assertSee('کاتالوگ محتوا')
            ->assertSee('ثبت سریع Hook')
            ->assertSee('محتوایی پیدا نشد');
    }

    public function test_catalog_renders_latest_snapshot_and_lumy_derived_metrics(): void
    {
        $account = $this->account();
        $content = $this->content($account, 'Latest reel metrics', 'reel', 'available', '2026-09-07T08:00:00Z');

        $content->metricSnapshots()->create([
            'captured_at' => '2026-09-07T10:00:00Z',
            'provider_updated_at' => '2026-09-07T10:00:00Z',
            'snapshot_type' => 'initial',
            'snapshot_window' => 'lifetime',
            'views' => 1000,
            'reach' => 800,
            'likes' => 10,
            'comments' => 5,
            'shares' => 10,
            'saves' => 20,
            'avg_watch_time_ms' => 4000,
            'video_duration_seconds' => 20,
            'skip_rate' => 60,
        ]);

        $content->metricSnapshots()->create([
            'captured_at' => '2026-09-07T11:00:00Z',
            'provider_updated_at' => '2026-09-07T11:00:00Z',
            'snapshot_type' => 'scheduled',
            'snapshot_window' => 'lifetime',
            'views' => 2000,
            'reach' => 1500,
            'likes' => 20,
            'comments' => 10,
            'shares' => 100,
            'saves' => 200,
            'avg_watch_time_ms' => 5000,
            'video_duration_seconds' => 10,
            'skip_rate' => 40,
        ]);

        $this->get(route('content.index'))
            ->assertOk()
            ->assertSee('Latest reel metrics')
            ->assertSee('2,000')
            ->assertSee('15.0%')
            ->assertSee('50.0%')
            ->assertSee('40.0%')
            ->assertSee('افزودن Hook');
    }

    public function test_catalog_filters_searches_and_sorts_by_latest_metric(): void
    {
        $account = $this->account();

        $alpha = $this->content($account, 'Alpha catalog item', 'reel', 'available', '2026-09-05T08:00:00Z');
        $beta = $this->content($account, 'Beta catalog item', 'carousel', 'available', '2026-09-06T08:00:00Z');
        $this->content($account, 'Gamma catalog item', 'reel', 'pending', '2026-09-07T08:00:00Z');

        $this->snapshot($alpha, 100, '2026-09-07T10:00:00Z');
        $this->snapshot($beta, 500, '2026-09-07T10:00:00Z');

        $this->get(route('content.index', ['type' => 'reel', 'status' => 'available']))
            ->assertOk()
            ->assertSee('Alpha catalog item')
            ->assertDontSee('Beta catalog item')
            ->assertDontSee('Gamma catalog item');

        $this->get(route('content.index', ['q' => 'Beta']))
            ->assertOk()
            ->assertSee('Beta catalog item')
            ->assertDontSee('Alpha catalog item');

        $this->get(route('content.index', ['sort' => 'views', 'dir' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder([
                'Beta catalog item',
                'Alpha catalog item',
            ]);
    }

    public function test_catalog_can_focus_on_contents_missing_a_primary_hook(): void
    {
        $account = $this->account();
        $missing = $this->content($account, 'Missing primary hook', 'reel', 'available', '2026-09-07T08:00:00Z');
        $complete = $this->content($account, 'Has primary hook', 'carousel', 'available', '2026-09-06T08:00:00Z');

        $complete->hooks()->create([
            'text' => 'A useful primary hook',
            'type' => 'curiosity',
            'source' => 'cover',
            'position' => 0,
            'is_primary' => true,
        ]);

        $this->get(route('content.index', ['intel' => 'missing_hook']))
            ->assertOk()
            ->assertSee('Missing primary hook')
            ->assertDontSee('Has primary hook')
            ->assertSee('هنوز Primary Hook ثبت نشده است.')
            ->assertSee('href="'.route('content.hooks', ['content' => $missing->id]).'"', false);
    }

    public function test_catalog_surfaces_primary_hook_context_and_annotation_state(): void
    {
        $account = $this->account();
        $content = $this->content($account, 'Polished catalog item', 'reel', 'available', '2026-09-07T08:00:00Z');

        $content->annotation()->create([
            'goal' => 'education',
            'annotated_at' => '2026-09-07T12:00:00Z',
        ]);
        $content->hooks()->create([
            'text' => 'Why does this Linux command matter?',
            'type' => 'question',
            'source' => 'video_overlay',
            'position' => 0,
            'is_primary' => true,
        ]);

        $this->get(route('content.index'))
            ->assertOk()
            ->assertSee('Polished catalog item')
            ->assertSee('Why does this Linux command matter?')
            ->assertSee('question · video_overlay')
            ->assertSee('Annotated')
            ->assertSee('ویرایش Hook')
            ->assertSee('Full Annotation');
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

    private function content(
        SocialAccount $account,
        string $caption,
        string $contentType,
        string $analyticsStatus,
        string $publishedAt,
    ): Content {
        static $sequence = 0;
        $sequence++;

        return $account->contents()->create([
            'platform_post_id' => 'post_'.$sequence,
            'permalink' => 'https://www.instagram.com/p/post'.$sequence.'/',
            'caption' => $caption,
            'content_type' => $contentType,
            'analytics_status' => $analyticsStatus,
            'published_at' => $publishedAt,
        ]);
    }

    private function snapshot(Content $content, int $views, string $providerUpdatedAt): void
    {
        $content->metricSnapshots()->create([
            'captured_at' => $providerUpdatedAt,
            'provider_updated_at' => $providerUpdatedAt,
            'snapshot_type' => 'initial',
            'snapshot_window' => 'lifetime',
            'views' => $views,
            'reach' => $views,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
        ]);
    }
}
