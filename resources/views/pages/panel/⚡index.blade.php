<?php

use App\Analytics\DashboardAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.panel')] class extends Component
{
    #[Computed]
    public function dashboard(): array
    {
        return app(DashboardAnalytics::class)->build();
    }

    public function formatCount(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value)
            ? '—'
            : number_format((float) $value, 0);
    }

    public function formatRate(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value)
            ? '—'
            : number_format((float) $value * 100, 1).'%';
    }

    public function formatProviderPercent(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value)
            ? '—'
            : number_format((float) $value, 1).'%';
    }

    public function formatDelta(?int $value): string
    {
        if ($value === null) {
            return '—';
        }

        return ($value > 0 ? '+' : '').number_format($value);
    }

    public function genderLabel(string $dimension): string
    {
        return match ($dimension) {
            'F' => 'زن',
            'M' => 'مرد',
            'U' => 'نامشخص',
            default => $dimension,
        };
    }

    public function contentLabel(\App\Models\Content $content): string
    {
        return \Illuminate\Support\Str::limit($content->caption ?: $content->platform_post_id, 72);
    }
};
?>

@php($dashboard = $this->dashboard)
@php($account = $dashboard['account'])
@php($accountInsight = $dashboard['account_insight'])
@php($growth = $dashboard['growth'])
@php($content = $dashboard['content'])
@php($reels = $dashboard['reels'])
@php($audience = $dashboard['audience'])

<div class="space-y-6">
    @if($account === null)
        <div class="rounded-2xl border border-base-300 bg-base-100 p-8 text-center shadow-sm">
            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-base-200">
                <x-icon name="o-chart-bar" class="size-6 text-base-content/45" />
            </div>
            <h1 class="mt-4 text-xl font-black">هنوز داده‌ای برای Dashboard وجود ندارد</h1>
            <p class="mt-2 text-sm text-base-content/55">ابتدا یک Instagram account را sync کن تا Lumy بتواند projection تحلیلی بسازد.</p>
        </div>
    @else
        <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">Analytics Dashboard</p>
                <h1 class="mt-1 text-2xl font-black lg:text-3xl">داشبورد Lumy</h1>
                <p class="mt-2 text-sm text-base-content/60">
                    @{{ $account->username }} · Facts + Derived Metrics · بدون ترکیب snapshotهای ناسازگار
                </p>
            </div>

            @if($accountInsight?->period_start || $accountInsight?->period_end)
                <div class="rounded-xl border border-base-300 bg-base-100 px-4 py-2 text-xs text-base-content/60">
                    Account Insights:
                    <span class="font-mono text-base-content">
                        {{ $accountInsight?->period_start?->utc()->format('Y-m-d') ?? '—' }}
                        →
                        {{ $accountInsight?->period_end?->utc()->format('Y-m-d') ?? '—' }}
                    </span>
                </div>
            @endif
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/55">Followers</div>
                <div class="mt-1 text-2xl font-black">{{ $this->formatCount($growth['followers_count']) }}</div>
                <div class="mt-1 text-xs {{ ($growth['followers_delta'] ?? 0) > 0 ? 'text-success' : (($growth['followers_delta'] ?? 0) < 0 ? 'text-error' : 'text-base-content/45') }}">
                    @if($growth['followers_delta'] === null)
                        baseline / history ناکافی
                    @else
                        {{ $this->formatDelta($growth['followers_delta']) }} نسبت به snapshot قبلی
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/55">Reach</div>
                <div class="mt-1 text-2xl font-black">{{ $this->formatCount($accountInsight?->reach) }}</div>
                <div class="mt-1 text-xs text-base-content/45">Account insight</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/55">Views</div>
                <div class="mt-1 text-2xl font-black">{{ $this->formatCount($accountInsight?->views) }}</div>
                <div class="mt-1 text-xs text-base-content/45">Account insight</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/55">Interactions</div>
                <div class="mt-1 text-2xl font-black">{{ $this->formatCount($accountInsight?->total_interactions) }}</div>
                <div class="mt-1 text-xs text-base-content/45">Provider account total</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/55">Analytics Coverage</div>
                <div class="mt-1 text-2xl font-black">{{ $this->formatRate($content['analytics_coverage']) }}</div>
                <div class="mt-1 text-xs text-base-content/45">
                    {{ number_format($content['with_analytics']) }} / {{ number_format($content['total']) }} محتوا
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-base-content/45">Growth</p>
                        <h2 class="mt-1 text-lg font-black">Follower snapshots</h2>
                    </div>
                    <span class="badge badge-outline">{{ $growth['history']->count() }} snapshot</span>
                </div>

                @if($growth['history']->isEmpty())
                    <p class="mt-6 text-sm text-base-content/55">هنوز follower snapshot ثبت نشده است.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                            <tr>
                                <th>زمان</th>
                                <th>Followers</th>
                                <th>Gained</th>
                                <th>Lost</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($growth['history']->take(7) as $snapshot)
                                <tr>
                                    <td class="font-mono text-xs">
                                        {{ ($snapshot->provider_updated_at ?? $snapshot->captured_at)?->utc()->format('Y-m-d H:i') ?? '—' }}
                                    </td>
                                    <td class="font-mono">{{ $this->formatCount($snapshot->followers_count) }}</td>
                                    <td class="font-mono">{{ $this->formatCount($snapshot->followers_gained) }}</td>
                                    <td class="font-mono">{{ $this->formatCount($snapshot->followers_lost) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-base-content/45">Content</p>
                        <h2 class="mt-1 text-lg font-black">Data coverage</h2>
                    </div>
                    <a wire:navigate href="{{ route('content.index') }}" class="btn btn-ghost btn-sm">کاتالوگ محتوا</a>
                </div>

                <div class="mt-5 space-y-5">
                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span>Analytics</span>
                            <span class="font-mono">{{ $content['with_analytics'] }}/{{ $content['total'] }} · {{ $this->formatRate($content['analytics_coverage']) }}</span>
                        </div>
                        <progress class="progress progress-primary w-full" value="{{ ($content['analytics_coverage'] ?? 0) * 100 }}" max="100"></progress>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span>Manual Annotation</span>
                            <span class="font-mono">{{ $content['annotated'] }}/{{ $content['total'] }} · {{ $this->formatRate($content['annotation_coverage']) }}</span>
                        </div>
                        <progress class="progress progress-secondary w-full" value="{{ ($content['annotation_coverage'] ?? 0) * 100 }}" max="100"></progress>
                    </div>
                </div>

                <div class="mt-5 rounded-xl bg-base-200 p-4 text-sm text-base-content/60">
                    Annotation coverage نشان می‌دهد چه مقدار از Content History به Content DNA قابل تحلیل تبدیل شده است.
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm xl:col-span-2">
                <div>
                    <p class="text-xs font-semibold uppercase text-base-content/45">Content Performance</p>
                    <h2 class="mt-1 text-lg font-black">Top content</h2>
                </div>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-bold">بیشترین Views</h3>
                        <div class="space-y-2">
                            @forelse($content['top_views'] as $index => $item)
                                <div class="flex items-center gap-3 rounded-xl border border-base-300 p-3">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-base-200 text-xs font-black">{{ $index + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">{{ $this->contentLabel($item['content']) }}</div>
                                        <div class="mt-1 text-xs text-base-content/45">{{ ucfirst($item['content']->content_type ?? 'content') }}</div>
                                    </div>
                                    <span class="font-mono text-sm font-bold">{{ $this->formatCount($item['value']) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-bold">بیشترین High Intent Rate</h3>
                        <div class="space-y-2">
                            @forelse($content['top_high_intent'] as $index => $item)
                                <div class="flex items-center gap-3 rounded-xl border border-base-300 p-3">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-base-200 text-xs font-black">{{ $index + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold">{{ $this->contentLabel($item['content']) }}</div>
                                        <div class="mt-1 text-xs text-base-content/45">Saves + Shares / Views</div>
                                    </div>
                                    <span class="font-mono text-sm font-bold">{{ $this->formatRate($item['value']) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-base-content/45">Account Interaction Mix</p>
                <h2 class="mt-1 text-lg font-black">Latest account insight</h2>

                <dl class="mt-5 space-y-3 text-sm">
                    @foreach([
                        'Likes' => $accountInsight?->likes,
                        'Comments' => $accountInsight?->comments,
                        'Saves' => $accountInsight?->saves,
                        'Shares' => $accountInsight?->shares,
                        'Reposts' => $accountInsight?->reposts,
                        'Accounts engaged' => $accountInsight?->accounts_engaged,
                    ] as $label => $value)
                        <div class="flex items-center justify-between gap-4 border-b border-base-200 pb-2 last:border-0">
                            <dt class="text-base-content/55">{{ $label }}</dt>
                            <dd class="font-mono font-bold">{{ $this->formatCount($value) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-base-content/45">Reels</p>
                    <h2 class="mt-1 text-lg font-black">Retention & Skip behavior</h2>
                </div>
                <div class="text-xs text-base-content/50">Median روی latest snapshot هر Reel محاسبه می‌شود.</div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl bg-base-200 p-4">
                    <div class="text-xs text-base-content/50">Reels</div>
                    <div class="mt-1 text-xl font-black">{{ number_format($reels['total']) }}</div>
                </div>
                <div class="rounded-xl bg-base-200 p-4">
                    <div class="text-xs text-base-content/50">Advanced coverage</div>
                    <div class="mt-1 text-xl font-black">{{ $this->formatRate($reels['advanced_coverage']) }}</div>
                    <div class="mt-1 text-xs text-base-content/40">{{ $reels['advanced'] }}/{{ $reels['total'] }}</div>
                </div>
                <div class="rounded-xl bg-base-200 p-4">
                    <div class="text-xs text-base-content/50">Median retention</div>
                    <div class="mt-1 text-xl font-black">{{ $this->formatRate($reels['median_retention']) }}</div>
                </div>
                <div class="rounded-xl bg-base-200 p-4">
                    <div class="text-xs text-base-content/50">Median skip rate</div>
                    <div class="mt-1 text-xl font-black">{{ $this->formatProviderPercent($reels['median_skip_rate']) }}</div>
                </div>
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <div>
                    <h3 class="mb-3 text-sm font-bold">Top Reels by Views</h3>
                    <div class="space-y-2">
                        @forelse($reels['top_views'] as $index => $item)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-base-300 p-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ $index + 1 }}. {{ $this->contentLabel($item['content']) }}</div>
                                    <div class="mt-1 text-xs text-base-content/45">{{ $item['content']->published_at?->utc()->format('Y-m-d') ?? '—' }}</div>
                                </div>
                                <span class="font-mono font-bold">{{ $this->formatCount($item['value']) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-bold">Top Reels by Retention</h3>
                    <div class="space-y-2">
                        @forelse($reels['top_retention'] as $index => $item)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-base-300 p-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ $index + 1 }}. {{ $this->contentLabel($item['content']) }}</div>
                                    <div class="mt-1 text-xs text-base-content/45">Avg watch / duration</div>
                                </div>
                                <span class="font-mono font-bold">{{ $this->formatRate($item['value']) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-base-content/45">Audience</p>
                    <h2 class="mt-1 text-lg font-black">Follower demographics</h2>
                </div>
                <div class="text-xs text-base-content/45">
                    {{ $audience['captured_at']?->utc()->format('Y-m-d H:i') ?? 'بدون snapshot' }}
                </div>
            </div>

            <div class="mt-5 grid gap-6 xl:grid-cols-4">
                <div>
                    <h3 class="mb-3 text-sm font-bold">سن</h3>
                    <div class="space-y-3">
                        @forelse($audience['age'] as $item)
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span>{{ $item['row']->dimension }}</span>
                                    <span class="font-mono">{{ $this->formatRate($item['share']) }}</span>
                                </div>
                                <progress class="progress progress-primary w-full" value="{{ ($item['share'] ?? 0) * 100 }}" max="100"></progress>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 text-sm font-bold">جنسیت</h3>
                    <div class="space-y-3">
                        @forelse($audience['gender'] as $item)
                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span>{{ $this->genderLabel($item['row']->dimension) }}</span>
                                    <span class="font-mono">{{ $this->formatRate($item['share']) }}</span>
                                </div>
                                <progress class="progress progress-secondary w-full" value="{{ ($item['share'] ?? 0) * 100 }}" max="100"></progress>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold">Top Cities</h3>
                        <span class="badge badge-ghost badge-xs">partial</span>
                    </div>
                    <div class="space-y-2">
                        @forelse($audience['cities'] as $index => $row)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-base-200 px-3 py-2 text-sm">
                                <span class="truncate">{{ $index + 1 }}. {{ $row->dimension }}</span>
                                <span class="font-mono font-bold">{{ $this->formatCount($row->value) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-bold">Top Countries</h3>
                        <span class="badge badge-ghost badge-xs">partial</span>
                    </div>
                    <div class="space-y-2">
                        @forelse($audience['countries'] as $index => $row)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-base-200 px-3 py-2 text-sm">
                                <span class="truncate">{{ $index + 1 }}. {{ $row->dimension }}</span>
                                <span class="font-mono font-bold">{{ $this->formatCount($row->value) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
