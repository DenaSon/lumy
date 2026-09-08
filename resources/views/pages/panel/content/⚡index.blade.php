<?php

use App\Models\Content;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.panel')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: 'all')]
    public string $contentType = 'all';

    #[Url(as: 'status', except: 'all')]
    public string $analyticsStatus = 'all';

    #[Url(as: 'intel', except: 'all')]
    public string $intelligenceStatus = 'all';

    #[Url(as: 'sort', except: 'published_at')]
    public string $sort = 'published_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $direction = 'desc';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedContentType(): void
    {
        $this->resetPage();
    }

    public function updatedAnalyticsStatus(): void
    {
        $this->resetPage();
    }

    public function updatedIntelligenceStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedDirection(): void
    {
        $this->resetPage();
    }

    public function setContentType(string $type): void
    {
        $this->contentType = in_array($type, ['all', 'reel', 'carousel', 'feed'], true) ? $type : 'all';
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'contentType', 'analyticsStatus', 'intelligenceStatus', 'sort', 'direction');
        $this->resetPage();
    }

    #[Computed]
    public function contents(): LengthAwarePaginator
    {
        $search = trim($this->search);
        $contentType = in_array($this->contentType, ['reel', 'carousel', 'feed'], true)
            ? $this->contentType
            : 'all';
        $analyticsStatus = in_array($this->analyticsStatus, ['available', 'pending', 'unavailable', 'failed'], true)
            ? $this->analyticsStatus
            : 'all';
        $intelligenceStatus = in_array($this->intelligenceStatus, ['missing_hook', 'has_hook', 'annotated', 'unannotated'], true)
            ? $this->intelligenceStatus
            : 'all';
        $sort = in_array($this->sort, ['published_at', 'views', 'reach', 'saves', 'shares'], true)
            ? $this->sort
            : 'published_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        $query = Content::query()
            ->with([
                'socialAccount:id,username',
                'coverMedia',
                'latestMetricSnapshot',
                'annotation:id,content_id,annotated_at',
                'hooks' => fn ($query) => $query
                    ->where('is_primary', true)
                    ->select(['id', 'content_id', 'text', 'type', 'source', 'position', 'is_primary']),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('caption', 'like', "%{$search}%")
                        ->orWhere('platform_post_id', 'like', "%{$search}%")
                        ->orWhere('permalink', 'like', "%{$search}%");
                });
            })
            ->when($contentType !== 'all', fn ($query) => $query->where('content_type', $contentType))
            ->when($analyticsStatus !== 'all', fn ($query) => $query->where('analytics_status', $analyticsStatus))
            ->when($intelligenceStatus === 'missing_hook', fn ($query) => $query->whereDoesntHave('hooks', fn ($query) => $query->where('is_primary', true)))
            ->when($intelligenceStatus === 'has_hook', fn ($query) => $query->whereHas('hooks', fn ($query) => $query->where('is_primary', true)))
            ->when($intelligenceStatus === 'annotated', fn ($query) => $query->whereHas('annotation'))
            ->when($intelligenceStatus === 'unannotated', fn ($query) => $query->whereDoesntHave('annotation'));

        if ($sort === 'published_at') {
            $query->orderBy('published_at', $direction)->orderBy('id', $direction);
        } else {
            $query
                ->orderByLatestMetric($sort, $direction)
                ->orderByDesc('published_at')
                ->orderByDesc('id');
        }

        return $query->paginate(24);
    }

    #[Computed]
    public function summary(): array
    {
        $total = Content::query()->count();
        $available = Content::query()->where('analytics_status', 'available')->count();
        $annotated = Content::query()->has('annotation')->count();
        $withPrimaryHook = Content::query()
            ->whereHas('hooks', fn ($query) => $query->where('is_primary', true))
            ->count();

        return [
            'total' => $total,
            'available' => $available,
            'pending' => Content::query()->where('analytics_status', 'pending')->count(),
            'annotated' => $annotated,
            'primary_hooks' => $withPrimaryHook,
            'missing_hooks' => max(0, $total - $withPrimaryHook),
            'analytics_rate' => $this->ratio($available, $total),
            'annotation_rate' => $this->ratio($annotated, $total),
            'hook_rate' => $this->ratio($withPrimaryHook, $total),
            'reels' => Content::query()->where('content_type', 'reel')->count(),
            'carousels' => Content::query()->where('content_type', 'carousel')->count(),
            'feeds' => Content::query()->where('content_type', 'feed')->count(),
        ];
    }

    public function formatCount(int|float|null $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 0);
    }

    public function formatRate(?float $value): string
    {
        return $value === null ? '—' : number_format($value * 100, 1).'%';
    }

    public function formatProviderPercent(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value)
            ? '—'
            : number_format((float) $value, 1).'%';
    }

    private function ratio(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? $numerator / $denominator : null;
    }
};
?>

@php($summary = $this->summary)
@php($contents = $this->contents)
@php($hasFilters = $search !== '' || $contentType !== 'all' || $analyticsStatus !== 'all' || $intelligenceStatus !== 'all' || $sort !== 'published_at' || $direction !== 'desc')

<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-primary">Content Catalog</p>
            <h1 class="mt-1 text-2xl font-black lg:text-3xl">کاتالوگ محتوا</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-base-content/60">
                همه‌ی محتواها، آخرین performance و وضعیت Content Intelligence در یک نمای سریع و بدون اسکرول افقی.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a wire:navigate href="{{ route('content.hooks') }}" class="btn btn-primary btn-sm">
                <x-icon name="o-bolt" class="size-4" />
                ثبت سریع Hook
                @if($summary['missing_hooks'] > 0)
                    <span class="badge badge-primary-content badge-sm">{{ number_format($summary['missing_hooks']) }}</span>
                @endif
            </a>
            <a wire:navigate href="{{ route('intelligence.analysis') }}" class="btn btn-outline btn-sm">
                تحلیل Intelligence
            </a>
        </div>
    </header>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-base-content/55">کل محتوا</span>
                <span class="badge badge-ghost badge-sm">Library</span>
            </div>
            <div class="mt-2 text-2xl font-black">{{ number_format($summary['total']) }}</div>
            <div class="mt-1 text-xs text-base-content/45">
                {{ number_format($summary['reels']) }} Reel · {{ number_format($summary['carousels']) }} Carousel · {{ number_format($summary['feeds']) }} Feed
            </div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-base-content/55">Analytics آماده</span>
                <span class="text-xs font-mono text-base-content/45">{{ $this->formatRate($summary['analytics_rate']) }}</span>
            </div>
            <div class="mt-2 text-2xl font-black">{{ number_format($summary['available']) }}</div>
            <progress class="progress progress-primary mt-3 w-full" value="{{ ($summary['analytics_rate'] ?? 0) * 100 }}" max="100"></progress>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-base-content/55">Annotation</span>
                <span class="text-xs font-mono text-base-content/45">{{ $this->formatRate($summary['annotation_rate']) }}</span>
            </div>
            <div class="mt-2 text-2xl font-black">{{ number_format($summary['annotated']) }}</div>
            <progress class="progress progress-secondary mt-3 w-full" value="{{ ($summary['annotation_rate'] ?? 0) * 100 }}" max="100"></progress>
        </div>

        <button wire:click="$set('intelligenceStatus', 'missing_hook')" type="button" class="rounded-2xl border border-base-300 bg-base-100 p-4 text-right shadow-sm transition hover:border-primary/40 hover:bg-primary/5">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-base-content/55">Primary Hook</span>
                <span class="text-xs font-mono text-base-content/45">{{ $this->formatRate($summary['hook_rate']) }}</span>
            </div>
            <div class="mt-2 flex items-end justify-between gap-2">
                <span class="text-2xl font-black">{{ number_format($summary['primary_hooks']) }}</span>
                @if($summary['missing_hooks'] > 0)
                    <span class="badge badge-warning badge-sm">{{ number_format($summary['missing_hooks']) }} باقی‌مانده</span>
                @else
                    <span class="badge badge-success badge-sm">کامل</span>
                @endif
            </div>
            <progress class="progress progress-accent mt-3 w-full" value="{{ ($summary['hook_rate'] ?? 0) * 100 }}" max="100"></progress>
        </button>
    </section>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <div class="flex flex-col gap-4">
            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <label class="form-control">
                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">جستجو</span>
                    <div class="relative">
                        <x-icon name="o-magnifying-glass" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-base-content/35" />
                        <input
                            wire:model.live.debounce.350ms="search"
                            type="search"
                            class="input input-bordered w-full pr-10"
                            placeholder="کپشن، Post ID یا permalink"
                        >
                    </div>
                </label>

                <div class="flex flex-wrap gap-2">
                    @foreach([
                        'all' => ['همه', $summary['total']],
                        'reel' => ['Reel', $summary['reels']],
                        'carousel' => ['Carousel', $summary['carousels']],
                        'feed' => ['Feed', $summary['feeds']],
                    ] as $value => [$label, $count])
                        <button
                            wire:click="setContentType('{{ $value }}')"
                            type="button"
                            class="btn btn-sm {{ $contentType === $value ? 'btn-primary' : 'btn-ghost border border-base-300' }}"
                        >
                            {{ $label }}
                            <span class="text-xs opacity-60">{{ number_format($count) }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="form-control">
                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Analytics</span>
                    <select wire:model.live="analyticsStatus" class="select select-bordered w-full">
                        <option value="all">همه وضعیت‌ها</option>
                        <option value="available">Available</option>
                        <option value="pending">Pending</option>
                        <option value="unavailable">Unavailable</option>
                        <option value="failed">Failed</option>
                    </select>
                </label>

                <label class="form-control">
                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">Intelligence</span>
                    <select wire:model.live="intelligenceStatus" class="select select-bordered w-full">
                        <option value="all">همه</option>
                        <option value="missing_hook">Primary Hook ندارد</option>
                        <option value="has_hook">Primary Hook دارد</option>
                        <option value="unannotated">Annotation نشده</option>
                        <option value="annotated">Annotation شده</option>
                    </select>
                </label>

                <label class="form-control">
                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">مرتب‌سازی</span>
                    <select wire:model.live="sort" class="select select-bordered w-full">
                        <option value="published_at">تاریخ انتشار</option>
                        <option value="views">Views</option>
                        <option value="reach">Reach</option>
                        <option value="saves">Saves</option>
                        <option value="shares">Shares</option>
                    </select>
                </label>

                <label class="form-control">
                    <span class="label-text mb-1 text-xs font-semibold text-base-content/60">جهت</span>
                    <select wire:model.live="direction" class="select select-bordered w-full">
                        <option value="desc">بیشترین / جدیدترین</option>
                        <option value="asc">کمترین / قدیمی‌ترین</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-200 pt-3 text-xs text-base-content/50">
                <span>{{ number_format($contents->total()) }} نتیجه · صفحه {{ $contents->currentPage() }} از {{ $contents->lastPage() }}</span>
                @if($hasFilters)
                    <button wire:click="clearFilters" type="button" class="btn btn-ghost btn-xs">
                        <x-icon name="o-x-mark" class="size-4" />
                        پاک‌کردن فیلترها
                    </button>
                @endif
            </div>
        </div>
    </section>

    <section>
        @if($contents->isEmpty())
            <div class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-base-300 bg-base-100 px-6 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-base-200">
                    <x-icon name="o-magnifying-glass" class="size-6 text-base-content/45" />
                </div>
                <h2 class="mt-4 font-bold">محتوایی پیدا نشد</h2>
                <p class="mt-1 text-sm text-base-content/55">فیلترها یا عبارت جستجو را تغییر بده.</p>
                @if($hasFilters)
                    <button wire:click="clearFilters" type="button" class="btn btn-outline btn-sm mt-4">نمایش همه محتواها</button>
                @endif
            </div>
        @else
            <div class="space-y-3">
                @foreach($contents as $content)
                    @php($snapshot = $content->latestMetricSnapshot)
                    @php($derived = $snapshot?->derivedMetrics())
                    @php($cover = $content->coverMedia)
                    @php($thumbnail = $cover?->thumbnail_url ?: $cover?->remote_url)
                    @php($primaryHook = $content->hooks->first())
                    @php($typeLabel = match($content->content_type) { 'reel' => 'Reel', 'carousel' => 'Carousel', 'feed' => 'Feed', default => $content->content_type ?: 'Unknown' })
                    @php($statusClass = match($content->analytics_status) { 'available' => 'badge-success', 'pending' => 'badge-warning', 'unavailable' => 'badge-neutral', 'failed' => 'badge-error', default => 'badge-ghost' })

                    <article wire:key="content-{{ $content->id }}" class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm transition hover:border-base-content/20 hover:shadow-md">
                        <div class="grid gap-4 xl:grid-cols-12 xl:items-center">
                            <div class="xl:col-span-2">
                                <div class="mx-auto aspect-[4/5] w-28 overflow-hidden rounded-2xl bg-base-200 xl:mx-0 xl:w-full xl:max-w-36">
                                    @if($thumbnail)
                                        <img src="{{ $thumbnail }}" alt="" loading="lazy" class="size-full object-cover">
                                    @else
                                        <div class="flex size-full items-center justify-center">
                                            <x-icon name="o-photo" class="size-7 text-base-content/30" />
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="min-w-0 xl:col-span-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge badge-outline badge-sm">{{ $typeLabel }}</span>
                                    <span class="badge {{ $statusClass }} badge-sm">{{ $content->analytics_status }}</span>
                                    @if($content->annotation?->annotated_at)
                                        <span class="badge badge-success badge-sm">Annotated</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">No annotation</span>
                                    @endif
                                </div>

                                <h2 class="mt-3 text-sm font-bold leading-7 lg:text-base">
                                    {{ \Illuminate\Support\Str::limit($content->caption ?: 'بدون کپشن', 150) }}
                                </h2>

                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-base-content/45">
                                    <span>{{ $content->published_at?->utc()->format('Y-m-d') ?? 'بدون تاریخ' }}</span>
                                    @if($content->socialAccount?->username)
                                        <span>@{{ $content->socialAccount->username }}</span>
                                    @endif
                                    <span class="font-mono">{{ $content->platform_post_id }}</span>
                                    @if($snapshot?->reach !== null)
                                        <span>Reach {{ $this->formatCount($snapshot->reach) }}</span>
                                    @endif
                                </div>

                                <div class="mt-3 rounded-xl {{ $primaryHook ? 'bg-primary/5 ring-1 ring-primary/15' : 'bg-warning/10 ring-1 ring-warning/20' }} p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-[11px] font-bold uppercase tracking-wide {{ $primaryHook ? 'text-primary' : 'text-warning' }}">Primary Hook</span>
                                        @if($primaryHook)
                                            <span class="text-[11px] text-base-content/45">
                                                {{ $primaryHook->type ?: 'بدون Type' }} · {{ $primaryHook->source ?: 'بدون Source' }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mt-1 text-sm leading-6 {{ $primaryHook ? 'font-semibold' : 'text-base-content/55' }}">
                                        {{ $primaryHook ? \Illuminate\Support\Str::limit($primaryHook->text, 110) : 'هنوز Primary Hook ثبت نشده است.' }}
                                    </div>
                                </div>
                            </div>

                            <div class="xl:col-span-4">
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    @foreach([
                                        ['Views', $this->formatCount($snapshot?->views)],
                                        ['High Intent', $this->formatRate($derived?->highIntentRate)],
                                        ['Saves', $this->formatCount($snapshot?->saves)],
                                        ['Shares', $this->formatCount($snapshot?->shares)],
                                        ['Retention', $this->formatRate($derived?->retentionRate)],
                                        ['Skip Rate', $this->formatProviderPercent($snapshot?->skip_rate)],
                                    ] as [$label, $value])
                                        <div class="rounded-xl bg-base-200/70 px-3 py-3">
                                            <div class="text-[11px] text-base-content/45">{{ $label }}</div>
                                            <div class="mt-1 font-mono text-sm font-black">{{ $value }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="xl:col-span-2">
                                <div class="grid gap-2 sm:grid-cols-3 xl:grid-cols-1">
                                    <a wire:navigate href="{{ route('content.hooks', ['content' => $content->id]) }}" class="btn {{ $primaryHook ? 'btn-outline' : 'btn-primary' }} btn-sm">
                                        <x-icon name="o-bolt" class="size-4" />
                                        {{ $primaryHook ? 'ویرایش Hook' : 'افزودن Hook' }}
                                    </a>
                                    <a wire:navigate href="{{ route('content.annotate', $content) }}" class="btn btn-ghost btn-sm border border-base-300">
                                        Full Annotation
                                    </a>
                                    @if($content->permalink)
                                        <a href="{{ $content->permalink }}" target="_blank" rel="noreferrer" class="btn btn-ghost btn-sm">
                                            Instagram
                                            <x-icon name="o-arrow-top-right-on-square" class="size-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-5 rounded-2xl border border-base-300 bg-base-100 px-4 py-4 shadow-sm">
                {{ $contents->links() }}
            </div>
        @endif
    </section>
</div>