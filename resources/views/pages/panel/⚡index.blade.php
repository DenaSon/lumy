<?php

use App\Analytics\DashboardAnalytics;
use Illuminate\Support\Collection;
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
        return \Illuminate\Support\Str::limit($content->caption ?: $content->platform_post_id, 68);
    }

    public function sparklinePoints(Collection $history): string
    {
        $values = $history
            ->reverse()
            ->pluck('followers_count')
            ->filter(fn ($value) => $value !== null && is_numeric($value))
            ->map(fn ($value) => (float) $value)
            ->values();

        $count = $values->count();

        if ($count < 2) {
            return '';
        }

        $min = (float) $values->min();
        $max = (float) $values->max();
        $range = max(1.0, $max - $min);

        return $values
            ->map(function (float $value, int $index) use ($count, $min, $range) {
                $x = ($index / ($count - 1)) * 100;
                $y = 32 - ((($value - $min) / $range) * 26 + 3);

                return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
            })
            ->implode(' ');
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
        <div class="rounded-3xl border border-base-300 bg-base-100 p-10 text-center shadow-sm">
            <div class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-base-200">
                <x-icon name="o-chart-bar" class="size-6 text-base-content/45" />
            </div>
            <h1 class="mt-4 text-xl font-black">هنوز داده‌ای برای Dashboard وجود ندارد</h1>
            <p class="mt-2 text-sm text-base-content/55">ابتدا Instagram account را sync کن تا Lumy بتواند وضعیت فعلی را نمایش دهد.</p>
        </div>
    @else
        @php($primaryHookMissing = max(0, $content['total'] - $content['primary_hooks']))
        @php($analyticsMissing = max(0, $content['total'] - $content['with_analytics']))
        @php($topAge = $audience['age']->sortByDesc('share')->first())
        @php($topGender = $audience['gender']->sortByDesc('share')->first())
        @php($topCity = $audience['cities']->first())
        @php($topCountry = $audience['countries']->first())
        @php($sparkline = $this->sparklinePoints($growth['history']))

        <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-primary">Analytics Dashboard</span>
                    <span class="badge badge-ghost badge-sm">@{{ $account->username }}</span>
                </div>
                <h1 class="mt-2 text-3xl font-black tracking-tight">داشبورد Lumy</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-base-content/55">
                    وضعیت فعلی پیج، عملکرد محتوا و کارهایی که برای کامل‌تر شدن Content Intelligence نیاز به توجه دارند.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($accountInsight?->period_start || $accountInsight?->period_end)
                    <div class="rounded-xl border border-base-300 bg-base-100 px-4 py-2 text-xs text-base-content/55">
                        <span class="font-semibold text-base-content">دوره Account Insights</span>
                        <span class="mr-2 font-mono">
                            {{ $accountInsight?->period_start?->utc()->format('Y-m-d') ?? '—' }}
                            →
                            {{ $accountInsight?->period_end?->utc()->format('Y-m-d') ?? '—' }}
                        </span>
                    </div>
                @endif

                <a wire:navigate href="{{ route('content.hooks') }}" class="btn btn-primary btn-sm">
                    <x-icon name="o-bolt" class="size-4" />
                    ثبت سریع Hook
                </a>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-6">
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs font-semibold text-base-content/50">Followers</div>
                <div class="mt-2 text-2xl font-black">{{ $this->formatCount($growth['followers_count']) }}</div>
                <div class="mt-1 text-xs {{ ($growth['followers_delta'] ?? 0) > 0 ? 'text-success' : (($growth['followers_delta'] ?? 0) < 0 ? 'text-error' : 'text-base-content/45') }}">
                    @if($growth['followers_delta'] === null)
                        history ناکافی
                    @else
                        {{ $this->formatDelta($growth['followers_delta']) }} نسبت به snapshot قبلی
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs font-semibold text-base-content/50">Reach</div>
                <div class="mt-2 text-2xl font-black">{{ $this->formatCount($accountInsight?->reach) }}</div>
                <div class="mt-1 text-xs text-base-content/40">Account insight</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs font-semibold text-base-content/50">Views</div>
                <div class="mt-2 text-2xl font-black">{{ $this->formatCount($accountInsight?->views) }}</div>
                <div class="mt-1 text-xs text-base-content/40">Account insight</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs font-semibold text-base-content/50">Median High Intent</div>
                <div class="mt-2 text-2xl font-black">{{ $this->formatRate($content['median_high_intent']) }}</div>
                <div class="mt-1 text-xs text-base-content/40">Saves + Shares / Views</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs font-semibold text-base-content/50">Median Retention</div>
                <div class="mt-2 text-2xl font-black">{{ $this->formatRate($reels['median_retention']) }}</div>
                <div class="mt-1 text-xs text-base-content/40">Reels with advanced data</div>
            </div>

            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs font-semibold text-base-content/50">Median Skip Rate</div>
                <div class="mt-2 text-2xl font-black">{{ $this->formatProviderPercent($reels['median_skip_rate']) }}</div>
                <div class="mt-1 text-xs text-base-content/40">Provider percent</div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm xl:col-span-7">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Growth</p>
                        <h2 class="mt-1 text-lg font-black">Follower trend</h2>
                    </div>
                    <span class="badge badge-outline">{{ $growth['history']->count() }} snapshot</span>
                </div>

                @if($sparkline === '')
                    <div class="mt-8 flex min-h-40 items-center justify-center rounded-2xl bg-base-200/60 px-6 text-center text-sm text-base-content/50">
                        برای نمایش روند حداقل دو follower snapshot لازم است.
                    </div>
                @else
                    <div class="mt-6 rounded-2xl bg-base-200/55 p-4">
                        <svg viewBox="0 0 100 36" class="h-44 w-full overflow-visible" preserveAspectRatio="none" role="img" aria-label="Follower trend">
                            <line x1="0" y1="32" x2="100" y2="32" class="stroke-base-content/10" stroke-width="0.6" />
                            <line x1="0" y1="18" x2="100" y2="18" class="stroke-base-content/10" stroke-width="0.6" />
                            <polyline points="{{ $sparkline }}" fill="none" class="stroke-primary" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                        </svg>
                    </div>
                @endif

                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-base-300 p-3">
                        <div class="text-xs text-base-content/45">Current</div>
                        <div class="mt-1 font-mono font-bold">{{ $this->formatCount($growth['followers_count']) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-3">
                        <div class="text-xs text-base-content/45">Change</div>
                        <div class="mt-1 font-mono font-bold">{{ $this->formatDelta($growth['followers_delta']) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-3">
                        <div class="text-xs text-base-content/45">History</div>
                        <div class="mt-1 font-mono font-bold">{{ number_format($growth['history']->count()) }}</div>
                    </div>
                    <div class="rounded-xl border border-base-300 p-3">
                        <div class="text-xs text-base-content/45">Latest snapshot</div>
                        <div class="mt-1 font-mono text-xs font-bold">
                            {{ ($growth['latest_snapshot']?->provider_updated_at ?? $growth['latest_snapshot']?->captured_at)?->utc()->format('Y-m-d') ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm xl:col-span-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Signals</p>
                    <h2 class="mt-1 text-lg font-black">نیازمند توجه</h2>
                    <p class="mt-1 text-xs leading-5 text-base-content/45">Rule-based و بدون AI؛ فقط بر اساس وضعیت فعلی داده.</p>
                </div>

                <div class="mt-5 space-y-3">
                    @if($primaryHookMissing > 0)
                        <a wire:navigate href="{{ route('content.hooks') }}" class="flex items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-4 transition hover:border-primary/40">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-content">
                                <x-icon name="o-bolt" class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold">{{ number_format($primaryHookMissing) }} محتوا Primary Hook ندارند</div>
                                <div class="mt-1 text-xs text-base-content/50">Quick Hook Queue را ادامه بده.</div>
                            </div>
                            <x-icon name="o-arrow-left" class="size-4 text-base-content/40" />
                        </a>
                    @else
                        <div class="flex items-center gap-3 rounded-2xl border border-success/20 bg-success/5 p-4">
                            <x-icon name="o-check-circle" class="size-5 text-success" />
                            <div>
                                <div class="font-bold">Primary Hook coverage کامل است</div>
                                <div class="mt-1 text-xs text-base-content/50">همه محتواها برای تحلیل Hook attribution آماده‌اند.</div>
                            </div>
                        </div>
                    @endif

                    @if($analyticsMissing > 0)
                        <a wire:navigate href="{{ route('content.index', ['status' => 'pending']) }}" class="flex items-center gap-3 rounded-2xl border border-base-300 p-4 transition hover:bg-base-200/60">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-200 font-black">{{ number_format($analyticsMissing) }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold">محتوا بدون latest analytics</div>
                                <div class="mt-1 text-xs text-base-content/50">Coverage فعلی {{ $this->formatRate($content['analytics_coverage']) }} است.</div>
                            </div>
                        </a>
                    @endif

                    @if($growth['followers_delta'] === null)
                        <div class="flex items-center gap-3 rounded-2xl border border-base-300 p-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-200">
                                <x-icon name="o-chart-bar" class="size-5" />
                            </div>
                            <div>
                                <div class="font-bold">Follower history هنوز برای trend کافی نیست</div>
                                <div class="mt-1 text-xs text-base-content/50">با snapshotهای بعدی تغییر واقعی قابل مشاهده می‌شود.</div>
                            </div>
                        </div>
                    @endif

                    @if(($reels['advanced_coverage'] ?? 1) < 1)
                        <div class="flex items-center gap-3 rounded-2xl border border-base-300 p-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-200 text-xs font-bold">
                                {{ $this->formatRate($reels['advanced_coverage']) }}
                            </div>
                            <div>
                                <div class="font-bold">Advanced Reel analytics کامل نیست</div>
                                <div class="mt-1 text-xs text-base-content/50">Retention و Skip فقط روی Reelهای دارای داده محاسبه شده‌اند.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Content Performance</p>
                    <h2 class="mt-1 text-lg font-black">محتواهای برتر</h2>
                    <p class="mt-1 text-xs text-base-content/45">Views برای reach و High Intent برای utility / intent behavior.</p>
                </div>
                <a wire:navigate href="{{ route('content.index') }}" class="btn btn-ghost btn-sm">مشاهده کاتالوگ</a>
            </div>

            <div class="mt-5 grid gap-6 xl:grid-cols-2">
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold">Top by Views</h3>
                        <span class="text-xs text-base-content/40">lifetime descriptive</span>
                    </div>
                    <div class="space-y-3">
                        @forelse($content['top_views']->take(3) as $index => $item)
                            @php($itemContent = $item['content'])
                            @php($itemCover = $itemContent->coverMedia)
                            @php($itemThumb = $itemCover?->thumbnail_url ?: $itemCover?->remote_url)
                            <a wire:navigate href="{{ route('content.annotate', $itemContent) }}" class="flex items-center gap-3 rounded-2xl border border-base-300 p-3 transition hover:bg-base-200/50">
                                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-base-200">
                                    @if($itemThumb)
                                        <img src="{{ $itemThumb }}" alt="" class="size-full object-cover" loading="lazy">
                                    @else
                                        <span class="text-sm font-black text-base-content/35">#{{ $index + 1 }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="line-clamp-2 text-sm font-bold leading-6">{{ $this->contentLabel($itemContent) }}</div>
                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-base-content/45">
                                        <span>{{ ucfirst($itemContent->content_type ?? 'content') }}</span>
                                        <span>High Intent {{ $this->formatRate($item['derived']?->highIntentRate) }}</span>
                                        <span>Retention {{ $this->formatRate($item['derived']?->retentionRate) }}</span>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="font-mono text-base font-black">{{ $this->formatCount($item['value']) }}</div>
                                    <div class="text-[10px] uppercase text-base-content/40">Views</div>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-2xl bg-base-200/60 p-5 text-sm text-base-content/50">داده‌ای موجود نیست.</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold">Top by High Intent</h3>
                        <span class="text-xs text-base-content/40">Saves + Shares / Views</span>
                    </div>
                    <div class="space-y-3">
                        @forelse($content['top_high_intent']->take(3) as $index => $item)
                            @php($itemContent = $item['content'])
                            @php($itemCover = $itemContent->coverMedia)
                            @php($itemThumb = $itemCover?->thumbnail_url ?: $itemCover?->remote_url)
                            <a wire:navigate href="{{ route('content.annotate', $itemContent) }}" class="flex items-center gap-3 rounded-2xl border border-base-300 p-3 transition hover:bg-base-200/50">
                                <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-base-200">
                                    @if($itemThumb)
                                        <img src="{{ $itemThumb }}" alt="" class="size-full object-cover" loading="lazy">
                                    @else
                                        <span class="text-sm font-black text-base-content/35">#{{ $index + 1 }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="line-clamp-2 text-sm font-bold leading-6">{{ $this->contentLabel($itemContent) }}</div>
                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-base-content/45">
                                        <span>{{ $this->formatCount($itemContent->latestMetricSnapshot?->views) }} Views</span>
                                        <span>{{ $this->formatCount($itemContent->latestMetricSnapshot?->saves) }} Saves</span>
                                        <span>{{ $this->formatCount($itemContent->latestMetricSnapshot?->shares) }} Shares</span>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="font-mono text-base font-black">{{ $this->formatRate($item['value']) }}</div>
                                    <div class="text-[10px] uppercase text-base-content/40">High Intent</div>
                                </div>
                            </a>
                        @empty
                            <div class="rounded-2xl bg-base-200/60 p-5 text-sm text-base-content/50">داده‌ای موجود نیست.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-12">
            <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm xl:col-span-7">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Reels</p>
                        <h2 class="mt-1 text-lg font-black">Retention & Skip</h2>
                    </div>
                    <a wire:navigate href="{{ route('intelligence.analysis') }}" class="btn btn-ghost btn-sm">تحلیل Intelligence</a>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-2xl bg-base-200/60 p-4">
                        <div class="text-xs text-base-content/45">Reels</div>
                        <div class="mt-1 text-xl font-black">{{ number_format($reels['total']) }}</div>
                    </div>
                    <div class="rounded-2xl bg-base-200/60 p-4">
                        <div class="text-xs text-base-content/45">Advanced coverage</div>
                        <div class="mt-1 text-xl font-black">{{ $this->formatRate($reels['advanced_coverage']) }}</div>
                        <div class="mt-1 text-xs text-base-content/40">{{ $reels['advanced'] }}/{{ $reels['total'] }}</div>
                    </div>
                    <div class="rounded-2xl bg-base-200/60 p-4">
                        <div class="text-xs text-base-content/45">Median retention</div>
                        <div class="mt-1 text-xl font-black">{{ $this->formatRate($reels['median_retention']) }}</div>
                    </div>
                    <div class="rounded-2xl bg-base-200/60 p-4">
                        <div class="text-xs text-base-content/45">Median skip</div>
                        <div class="mt-1 text-xl font-black">{{ $this->formatProviderPercent($reels['median_skip_rate']) }}</div>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <h3 class="mb-3 text-sm font-bold">Top Retention</h3>
                        <div class="space-y-2">
                            @forelse($reels['top_retention']->take(3) as $index => $item)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-base-300 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold">{{ $index + 1 }}. {{ $this->contentLabel($item['content']) }}</div>
                                    </div>
                                    <span class="font-mono text-sm font-bold">{{ $this->formatRate($item['value']) }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">داده‌ای موجود نیست.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-sm font-bold">Interaction mix</h3>
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            @foreach([
                                'Likes' => $accountInsight?->likes,
                                'Comments' => $accountInsight?->comments,
                                'Saves' => $accountInsight?->saves,
                                'Shares' => $accountInsight?->shares,
                            ] as $label => $value)
                                <div class="rounded-xl border border-base-300 p-3">
                                    <dt class="text-xs text-base-content/45">{{ $label }}</dt>
                                    <dd class="mt-1 font-mono font-bold">{{ $this->formatCount($value) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm xl:col-span-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Coverage</p>
                    <h2 class="mt-1 text-lg font-black">آمادگی داده برای Intelligence</h2>
                </div>

                <div class="mt-5 space-y-5">
                    @foreach([
                        ['label' => 'Analytics', 'count' => $content['with_analytics'], 'rate' => $content['analytics_coverage']],
                        ['label' => 'Manual Annotation', 'count' => $content['annotated'], 'rate' => $content['annotation_coverage']],
                        ['label' => 'Primary Hook', 'count' => $content['primary_hooks'], 'rate' => $content['primary_hook_coverage']],
                    ] as $coverage)
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold">{{ $coverage['label'] }}</span>
                                <span class="font-mono text-xs text-base-content/55">
                                    {{ number_format($coverage['count']) }}/{{ number_format($content['total']) }} · {{ $this->formatRate($coverage['rate']) }}
                                </span>
                            </div>
                            <progress class="progress progress-primary w-full" value="{{ ($coverage['rate'] ?? 0) * 100 }}" max="100"></progress>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 grid grid-cols-2 gap-2">
                    <a wire:navigate href="{{ route('content.hooks') }}" class="btn btn-primary btn-sm">ادامه Hookها</a>
                    <a wire:navigate href="{{ route('intelligence.index') }}" class="btn btn-outline btn-sm">Setup</a>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Audience</p>
                    <h2 class="mt-1 text-lg font-black">خلاصه مخاطب</h2>
                </div>
                <div class="text-xs text-base-content/45">
                    Snapshot: {{ $audience['captured_at']?->utc()->format('Y-m-d H:i') ?? '—' }}
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl bg-base-200/60 p-4">
                    <div class="text-xs text-base-content/45">Top Age</div>
                    <div class="mt-2 text-xl font-black">{{ $topAge['row']->dimension ?? '—' }}</div>
                    <div class="mt-1 text-xs text-base-content/45">{{ $this->formatRate($topAge['share'] ?? null) }}</div>
                </div>
                <div class="rounded-2xl bg-base-200/60 p-4">
                    <div class="text-xs text-base-content/45">Top Gender</div>
                    <div class="mt-2 text-xl font-black">{{ $topGender ? $this->genderLabel($topGender['row']->dimension) : '—' }}</div>
                    <div class="mt-1 text-xs text-base-content/45">{{ $this->formatRate($topGender['share'] ?? null) }}</div>
                </div>
                <div class="rounded-2xl bg-base-200/60 p-4">
                    <div class="text-xs text-base-content/45">Top City</div>
                    <div class="mt-2 truncate text-xl font-black">{{ $topCity?->dimension ?? '—' }}</div>
                    <div class="mt-1 text-xs text-base-content/45">partial demographic</div>
                </div>
                <div class="rounded-2xl bg-base-200/60 p-4">
                    <div class="text-xs text-base-content/45">Top Country</div>
                    <div class="mt-2 truncate text-xl font-black">{{ $topCountry?->dimension ?? '—' }}</div>
                    <div class="mt-1 text-xs text-base-content/45">partial demographic</div>
                </div>
            </div>
        </section>
    @endif
</div>
