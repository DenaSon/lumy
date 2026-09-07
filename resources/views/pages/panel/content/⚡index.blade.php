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

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedDirection(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'contentType', 'analyticsStatus', 'sort', 'direction');
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
        $sort = in_array($this->sort, ['published_at', 'views', 'reach', 'saves', 'shares'], true)
            ? $this->sort
            : 'published_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        $query = Content::query()
            ->with([
                'socialAccount:id,username',
                'coverMedia',
                'latestMetricSnapshot',
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
            ->when($analyticsStatus !== 'all', fn ($query) => $query->where('analytics_status', $analyticsStatus));

        if ($sort === 'published_at') {
            $query->orderBy('published_at', $direction);
        } else {
            $query
                ->orderByLatestMetric($sort, $direction)
                ->orderByDesc('published_at');
        }

        return $query->paginate(24);
    }

    #[Computed]
    public function summary(): array
    {
        return [
            'total' => Content::query()->count(),
            'available' => Content::query()->where('analytics_status', 'available')->count(),
            'pending' => Content::query()->where('analytics_status', 'pending')->count(),
            'reels' => Content::query()->where('content_type', 'reel')->count(),
            'carousels' => Content::query()->where('content_type', 'carousel')->count(),
        ];
    }

    public function formatCount(?int $value): string
    {
        return $value === null ? '—' : number_format($value);
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
};
?>

<div class="space-y-6">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-primary">Content Catalog</p>
            <h1 class="mt-1 text-2xl font-black lg:text-3xl">کاتالوگ محتوا</h1>
            <p class="mt-2 max-w-3xl text-sm text-base-content/60">
                نمای واحد محتوای Instagram با آخرین snapshot معتبر و KPIهای محاسبه‌شده‌ی Lumy.
            </p>
        </div>

        <div class="text-sm text-base-content/60">
            {{ number_format($this->contents->total()) }} نتیجه
        </div>
    </header>

    @php($summary = $this->summary)

    <section class="grid grid-cols-2 gap-3 md:grid-cols-5">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="text-xs text-base-content/55">کل محتوا</div>
            <div class="mt-1 text-2xl font-black">{{ number_format($summary['total']) }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="text-xs text-base-content/55">Analytics آماده</div>
            <div class="mt-1 text-2xl font-black">{{ number_format($summary['available']) }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="text-xs text-base-content/55">در انتظار Analytics</div>
            <div class="mt-1 text-2xl font-black">{{ number_format($summary['pending']) }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="text-xs text-base-content/55">Reel</div>
            <div class="mt-1 text-2xl font-black">{{ number_format($summary['reels']) }}</div>
        </div>
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="text-xs text-base-content/55">Carousel</div>
            <div class="mt-1 text-2xl font-black">{{ number_format($summary['carousels']) }}</div>
        </div>
    </section>

    <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <label class="form-control xl:col-span-2">
                <span class="label-text mb-1 text-xs font-semibold text-base-content/60">جستجو</span>
                <input
                    wire:model.live.debounce.350ms="search"
                    type="search"
                    class="input input-bordered w-full"
                    placeholder="کپشن، Post ID یا permalink"
                >
            </label>

            <label class="form-control">
                <span class="label-text mb-1 text-xs font-semibold text-base-content/60">نوع محتوا</span>
                <select wire:model.live="contentType" class="select select-bordered w-full">
                    <option value="all">همه</option>
                    <option value="reel">Reel</option>
                    <option value="carousel">Carousel</option>
                    <option value="feed">Feed</option>
                </select>
            </label>

            <label class="form-control">
                <span class="label-text mb-1 text-xs font-semibold text-base-content/60">وضعیت Analytics</span>
                <select wire:model.live="analyticsStatus" class="select select-bordered w-full">
                    <option value="all">همه</option>
                    <option value="available">Available</option>
                    <option value="pending">Pending</option>
                    <option value="unavailable">Unavailable</option>
                    <option value="failed">Failed</option>
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

        @if($search !== '' || $contentType !== 'all' || $analyticsStatus !== 'all' || $sort !== 'published_at' || $direction !== 'desc')
            <div class="mt-3 flex justify-end">
                <button wire:click="clearFilters" type="button" class="btn btn-ghost btn-sm">
                    <x-icon name="o-x-mark" class="size-4" />
                    پاک‌کردن فیلترها
                </button>
            </div>
        @endif
    </section>

    @php($contents = $this->contents)

    <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
        @if($contents->isEmpty())
            <div class="flex min-h-72 flex-col items-center justify-center px-6 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-base-200">
                    <x-icon name="o-magnifying-glass" class="size-6 text-base-content/50" />
                </div>
                <h2 class="mt-4 font-bold">محتوایی پیدا نشد</h2>
                <p class="mt-1 text-sm text-base-content/55">فیلترها یا عبارت جستجو را تغییر بده.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra min-w-[1180px]">
                    <thead>
                    <tr class="text-xs uppercase text-base-content/55">
                        <th>محتوا</th>
                        <th>انتشار</th>
                        <th>Views</th>
                        <th>Reach</th>
                        <th>Saves</th>
                        <th>Shares</th>
                        <th>High Intent</th>
                        <th>Retention</th>
                        <th>Skip Rate</th>
                        <th>Analytics</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($contents as $content)
                        @php($snapshot = $content->latestMetricSnapshot)
                        @php($derived = $snapshot?->derivedMetrics())
                        @php($cover = $content->coverMedia)
                        @php($thumbnail = $cover?->thumbnail_url ?: $cover?->remote_url)
                        @php($typeLabel = match($content->content_type) { 'reel' => 'Reel', 'carousel' => 'Carousel', 'feed' => 'Feed', default => $content->content_type ?: 'Unknown' })
                        @php($statusClass = match($content->analytics_status) { 'available' => 'badge-success', 'pending' => 'badge-warning', 'unavailable' => 'badge-neutral', 'failed' => 'badge-error', default => 'badge-ghost' })

                        <tr wire:key="content-{{ $content->id }}">
                            <td>
                                <div class="flex min-w-80 items-center gap-3">
                                    <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-base-200">
                                        @if($thumbnail)
                                            <img
                                                src="{{ $thumbnail }}"
                                                alt=""
                                                loading="lazy"
                                                class="size-full object-cover"
                                            >
                                        @else
                                            <x-icon name="o-photo" class="size-6 text-base-content/35" />
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <span class="badge badge-outline badge-sm">{{ $typeLabel }}</span>
                                            @if($content->socialAccount?->username)
                                                <span class="text-xs text-base-content/45">@{{ $content->socialAccount->username }}</span>
                                            @endif
                                        </div>

                                        <div class="max-w-sm text-sm font-semibold leading-6">
                                            {{ \Illuminate\Support\Str::limit($content->caption ?: 'بدون کپشن', 90) }}
                                        </div>

                                        <div class="mt-1 flex items-center gap-2 text-xs text-base-content/45">
                                            <span>{{ $content->platform_post_id }}</span>
                                            @if($content->permalink)
                                                <a
                                                    href="{{ $content->permalink }}"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    class="link link-primary no-underline"
                                                >
                                                    Instagram ↗
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-sm">
                                {{ $content->published_at?->utc()->format('Y-m-d') ?? '—' }}
                            </td>
                            <td class="font-mono text-sm">{{ $this->formatCount($snapshot?->views) }}</td>
                            <td class="font-mono text-sm">{{ $this->formatCount($snapshot?->reach) }}</td>
                            <td class="font-mono text-sm">{{ $this->formatCount($snapshot?->saves) }}</td>
                            <td class="font-mono text-sm">{{ $this->formatCount($snapshot?->shares) }}</td>
                            <td class="font-mono text-sm">{{ $this->formatRate($derived?->highIntentRate) }}</td>
                            <td class="font-mono text-sm">{{ $this->formatRate($derived?->retentionRate) }}</td>
                            <td class="font-mono text-sm">{{ $this->formatProviderPercent($snapshot?->skip_rate) }}</td>
                            <td>
                                <span class="badge {{ $statusClass }} badge-sm">
                                    {{ $content->analytics_status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-base-300 px-4 py-4">
                {{ $contents->links() }}
            </div>
        @endif
    </section>
</div>
