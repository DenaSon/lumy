<?php

use App\ContentIntelligence\ContentIntelligenceAnalytics;
use App\ContentIntelligence\ContentIntelligenceEvidence;
use App\Models\SocialAccount;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.panel')] class extends Component
{
    #[Url(as: 'dimension')]
    public string $dimension = 'primary_hook_type';

    #[Url(as: 'evidence', except: 'all')]
    public string $evidenceFilter = 'all';

    public function mount(): void
    {
        if (! array_key_exists($this->dimension, $this->dimensionOptions())) {
            $this->dimension = 'primary_hook_type';
        }

        if (! array_key_exists($this->evidenceFilter, $this->evidenceFilterOptions())) {
            $this->evidenceFilter = 'all';
        }
    }

    public function updatedDimension(): void
    {
        if (! array_key_exists($this->dimension, $this->dimensionOptions())) {
            $this->dimension = 'primary_hook_type';
        }

        unset($this->analysis, $this->evidence, $this->visibleEvidence);
    }

    public function updatedEvidenceFilter(): void
    {
        if (! array_key_exists($this->evidenceFilter, $this->evidenceFilterOptions())) {
            $this->evidenceFilter = 'all';
        }

        unset($this->visibleEvidence);
    }

    #[Computed]
    public function account(): ?SocialAccount
    {
        $configuredAccountId = trim((string) config('zernio.account_id'));

        if ($configuredAccountId !== '') {
            $configured = SocialAccount::query()
                ->where('provider', 'zernio')
                ->where('provider_account_id', $configuredAccountId)
                ->first();

            if ($configured !== null) {
                return $configured;
            }
        }

        return SocialAccount::query()
            ->where('platform', 'instagram')
            ->latest('id')
            ->first();
    }

    #[Computed]
    public function analysis(): ?array
    {
        if ($this->account === null) {
            return null;
        }

        return app(ContentIntelligenceAnalytics::class)
            ->analyzeDimension($this->account, $this->dimension);
    }

    #[Computed]
    public function evidence(): Collection
    {
        if ($this->account === null) {
            return collect();
        }

        return app(ContentIntelligenceEvidence::class)
            ->dimension($this->account, $this->dimension)
            ->sortBy(function (array $item) {
                $statusWeight = match ($item['evidence_status']) {
                    'usable' => 0,
                    'exploratory' => 1,
                    default => 2,
                };

                return [
                    $item['eligible'] ? 0 : 1,
                    $statusWeight,
                    -abs((float) ($item['relative_lift'] ?? $item['delta'] ?? 0)),
                    $item['group_label'],
                    $item['metric'],
                ];
            })
            ->values();
    }

    #[Computed]
    public function visibleEvidence(): Collection
    {
        return match ($this->evidenceFilter) {
            'eligible' => $this->evidence->where('eligible', true)->values(),
            'usable' => $this->evidence->where('evidence_status', 'usable')->values(),
            'exploratory' => $this->evidence->where('evidence_status', 'exploratory')->values(),
            default => $this->evidence,
        };
    }

    /** @return array<string, string> */
    public function dimensionOptions(): array
    {
        return [
            'primary_hook_type' => 'Hook Type',
            'primary_hook_source' => 'Hook Source',
            'primary_topic' => 'Topic',
            'primary_pillar' => 'Pillar',
            'format' => 'Format',
            'goal' => 'Goal',
            'cta_type' => 'CTA',
            'production_style' => 'Production Style',
        ];
    }

    /** @return array<string, string> */
    public function evidenceFilterOptions(): array
    {
        return [
            'all' => 'همه',
            'eligible' => 'Eligible',
            'usable' => 'Usable',
            'exploratory' => 'Exploratory',
        ];
    }

    /** @return array<string, string> */
    public function metricLabels(): array
    {
        return [
            'views' => 'Views',
            'reach' => 'Reach',
            'like_rate' => 'Like Rate',
            'comment_rate' => 'Comment Rate',
            'share_rate' => 'Share Rate',
            'save_rate' => 'Save Rate',
            'high_intent_rate' => 'High Intent',
            'engagement_per_view' => 'Engagement / View',
            'engagement_per_reach' => 'Engagement / Reach',
            'retention_rate' => 'Retention',
            'skip_rate' => 'Skip Rate',
        ];
    }

    /** @return list<string> */
    public function primaryMetrics(): array
    {
        return [
            'high_intent_rate',
            'save_rate',
            'share_rate',
            'retention_rate',
            'skip_rate',
        ];
    }

    /** @return array<string, int> */
    public function evidenceCounts(): array
    {
        return [
            'total' => $this->evidence->count(),
            'eligible' => $this->evidence->where('eligible', true)->count(),
            'usable' => $this->evidence->where('evidence_status', 'usable')->count(),
            'exploratory' => $this->evidence->where('evidence_status', 'exploratory')->count(),
            'insufficient' => $this->evidence->where('evidence_status', 'insufficient')->count(),
        ];
    }

    public function formatMetric(string $metric, int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (in_array($metric, ['views', 'reach'], true)) {
            return number_format((float) $value, 0);
        }

        if ($metric === 'skip_rate') {
            return number_format((float) $value, 1).'%';
        }

        return number_format((float) $value * 100, 1).'%';
    }

    public function formatDelta(string $metric, int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $sign = (float) $value > 0 ? '+' : '';

        if (in_array($metric, ['views', 'reach'], true)) {
            return $sign.number_format((float) $value, 0);
        }

        if ($metric === 'skip_rate') {
            return $sign.number_format((float) $value, 1).' pp';
        }

        return $sign.number_format((float) $value * 100, 1).' pp';
    }

    public function formatLift(int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $sign = (float) $value > 0 ? '+' : '';

        return $sign.number_format((float) $value * 100, 1).'%';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'usable' => 'Usable',
            'exploratory' => 'Exploratory',
            default => 'Insufficient',
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'usable' => 'badge-success',
            'exploratory' => 'badge-warning',
            default => 'badge-ghost',
        };
    }

    public function directionLabel(?string $direction): string
    {
        return match ($direction) {
            'above' => 'بالاتر از peers',
            'below' => 'پایین‌تر از peers',
            'same' => 'برابر با peers',
            default => 'بدون مقایسه',
        };
    }
};
?>

<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold text-primary">Content Intelligence</p>
            <h1 class="mt-1 text-2xl font-black lg:text-3xl">تحلیل الگوهای محتوا</h1>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-base-content/60">
                Content DNA را با performance واقعی مقایسه کن و الگوهایی را ببین که پشت آن‌ها sample، baseline و evidence مشخص وجود دارد.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a wire:navigate href="{{ route('content.hooks') }}" class="btn btn-primary btn-sm">
                <x-icon name="o-bolt" class="size-4" />
                Quick Hook
            </a>
            <a wire:navigate href="{{ route('content.index') }}" class="btn btn-outline btn-sm">Content Catalog</a>
            <a wire:navigate href="{{ route('intelligence.index') }}" class="btn btn-ghost btn-sm">Setup</a>
        </div>
    </header>

    @if($this->account === null)
        <x-panel.empty-state
            title="اکانت Instagram پیدا نشد"
            description="بعد از sync شدن Social Account، Content Intelligence در این بخش فعال می‌شود."
        />
    @else
        @php($analysis = $this->analysis)
        @php($baseline = $analysis['baseline'])
        @php($groups = $analysis['groups'])
        @php($evidenceCounts = $this->evidenceCounts())

        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-black">{{ '@'.$this->account->username }}</span>
                            <span class="badge badge-outline badge-sm">{{ $this->dimensionOptions()[$dimension] }}</span>
                        </div>
                        <p class="mt-1 text-xs text-base-content/50">Dimension را عوض کن تا همان داده‌ها با attribution متفاوت تحلیل شوند.</p>
                    </div>
                    <div class="text-xs text-base-content/45">URL state برای dimension حفظ می‌شود.</div>
                </div>

                <div class="flex gap-2 overflow-x-auto pb-1">
                    @foreach($this->dimensionOptions() as $key => $label)
                        <button
                            type="button"
                            wire:click="$set('dimension', '{{ $key }}')"
                            class="btn btn-sm shrink-0 {{ $dimension === $key ? 'btn-primary' : 'btn-ghost' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/50">Attributed Content</div>
                <div class="mt-1 text-2xl font-black">{{ number_format($analysis['attributed_sample_size']) }}</div>
                <div class="mt-1 text-xs text-base-content/40">برای {{ $this->dimensionOptions()[$dimension] }}</div>
            </div>
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/50">Groups</div>
                <div class="mt-1 text-2xl font-black">{{ number_format($groups->count()) }}</div>
                <div class="mt-1 text-xs text-base-content/40">Primary attribution only</div>
            </div>
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/50">Eligible Evidence</div>
                <div class="mt-1 text-2xl font-black">{{ number_format($evidenceCounts['eligible']) }}</div>
                <div class="mt-1 text-xs text-base-content/40">sample کافی برای surfacing</div>
            </div>
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="text-xs text-base-content/50">Usable Evidence</div>
                <div class="mt-1 text-2xl font-black">{{ number_format($evidenceCounts['usable']) }}</div>
                <div class="mt-1 text-xs text-base-content/40">V1 sample threshold</div>
            </div>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Reference</p>
                    <h2 class="mt-1 text-lg font-black">Overall baseline</h2>
                    <p class="mt-1 text-sm text-base-content/55">Median همه‌ی محتوای attributed در همین dimension.</p>
                </div>
                <div class="badge badge-outline">n={{ number_format($analysis['attributed_sample_size']) }}</div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach($this->primaryMetrics() as $metric)
                    @php($item = $baseline[$metric])
                    <div class="rounded-xl bg-base-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-base-content/55">{{ $this->metricLabels()[$metric] }}</span>
                            <span class="badge badge-xs {{ $this->statusClass($item['sample_status']) }}">{{ $this->statusLabel($item['sample_status']) }}</span>
                        </div>
                        <div class="mt-2 text-xl font-black">{{ $this->formatMetric($metric, $item['median']) }}</div>
                        <div class="mt-1 text-[11px] text-base-content/40">metric n={{ $item['sample_size'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Comparison</p>
                    <h2 class="mt-1 text-lg font-black">Group vs peer baseline</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-base-content/55">
                        هر گروه با بقیه‌ی گروه‌های همان dimension مقایسه می‌شود. Peer baseline گروه فعلی را از خودش حذف می‌کند.
                    </p>
                </div>
                <div class="text-xs text-base-content/45">Delta جهت عددی را نشان می‌دهد؛ نه خوب یا بد بودن آن.</div>
            </div>

            @if($groups->isEmpty())
                <div class="mt-5 rounded-xl bg-base-200 p-8 text-center text-sm text-base-content/55">برای این dimension هنوز attribution کافی وجود ندارد.</div>
            @else
                <div class="mt-5 space-y-4">
                    @foreach($groups as $group)
                        <article class="rounded-2xl border border-base-300 p-4 lg:p-5" wire:key="intelligence-group-{{ $dimension }}-{{ $group['key'] }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-black">{{ $group['label'] }}</h3>
                                        <span class="badge badge-sm {{ $this->statusClass($group['sample_status']) }}">{{ $this->statusLabel($group['sample_status']) }}</span>
                                    </div>
                                    @if(($group['meta']['pillar_name'] ?? null) !== null)
                                        <div class="mt-1 text-xs text-base-content/45">Pillar: {{ $group['meta']['pillar_name'] }}</div>
                                    @endif
                                </div>
                                <div class="flex gap-2 text-xs">
                                    <span class="rounded-lg bg-base-200 px-3 py-2 font-mono">group n={{ $group['sample_size'] }}</span>
                                    <span class="rounded-lg bg-base-200 px-3 py-2 font-mono">analytics n={{ $group['analytics_sample_size'] }}</span>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                @foreach($this->primaryMetrics() as $metric)
                                    @php($metricRow = $group['metrics'][$metric])
                                    @php($comparison = $group['comparisons'][$metric])
                                    <div class="rounded-xl bg-base-200 p-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-xs font-semibold text-base-content/55">{{ $this->metricLabels()[$metric] }}</span>
                                            <span class="badge badge-xs {{ $this->statusClass($comparison['evidence_status']) }}">{{ $this->statusLabel($comparison['evidence_status']) }}</span>
                                        </div>
                                        <div class="mt-3 flex items-end justify-between gap-3">
                                            <div>
                                                <div class="text-[10px] text-base-content/40">Group</div>
                                                <div class="font-mono text-lg font-black">{{ $this->formatMetric($metric, $metricRow['median']) }}</div>
                                            </div>
                                            <div class="text-left">
                                                <div class="text-[10px] text-base-content/40">Peers</div>
                                                <div class="font-mono text-sm font-bold">{{ $this->formatMetric($metric, $comparison['peer_median']) }}</div>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex items-center justify-between gap-2 border-t border-base-300 pt-2 text-[11px]">
                                            <span class="font-mono font-semibold">Δ {{ $this->formatDelta($metric, $comparison['delta']) }}</span>
                                            <span class="text-base-content/45">n={{ $metricRow['sample_size'] }}/{{ $comparison['peer_sample_size'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40">Evidence</p>
                    <h2 class="mt-1 text-lg font-black">Evidence candidates</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-base-content/55">
                        فقط behavior metricها. Views و Reach تا قبل از age-normalized checkpoints وارد Evidence نمی‌شوند.
                    </p>
                </div>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    @foreach($this->evidenceFilterOptions() as $key => $label)
                        <button
                            type="button"
                            wire:click="$set('evidenceFilter', '{{ $key }}')"
                            class="btn btn-xs shrink-0 {{ $evidenceFilter === $key ? 'btn-primary' : 'btn-ghost' }}"
                        >
                            {{ $label }}
                            @if($key === 'eligible')
                                <span class="opacity-60">{{ $evidenceCounts['eligible'] }}</span>
                            @elseif($key === 'usable')
                                <span class="opacity-60">{{ $evidenceCounts['usable'] }}</span>
                            @elseif($key === 'exploratory')
                                <span class="opacity-60">{{ $evidenceCounts['exploratory'] }}</span>
                            @else
                                <span class="opacity-60">{{ $evidenceCounts['total'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            @if($this->visibleEvidence->isEmpty())
                <div class="mt-5 rounded-xl bg-base-200 p-7 text-center text-sm text-base-content/55">Evidence منطبق با این فیلتر وجود ندارد.</div>
            @else
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->visibleEvidence->take(18) as $item)
                        <article class="rounded-2xl border border-base-300 p-4" wire:key="evidence-{{ $dimension }}-{{ $item['group_key'] }}-{{ $item['metric'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold text-primary">{{ $this->metricLabels()[$item['metric']] }}</div>
                                    <h3 class="mt-1 font-black">{{ $item['group_label'] }}</h3>
                                    @if(($item['group_meta']['pillar_name'] ?? null) !== null)
                                        <div class="mt-1 text-[11px] text-base-content/45">{{ $item['group_meta']['pillar_name'] }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="badge badge-sm {{ $this->statusClass($item['evidence_status']) }}">{{ $this->statusLabel($item['evidence_status']) }}</span>
                                    @if($item['eligible'])
                                        <span class="badge badge-primary badge-xs">Eligible</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-base-200 p-2">
                                    <div class="text-[10px] text-base-content/40">Group</div>
                                    <div class="mt-1 font-mono text-sm font-black">{{ $this->formatMetric($item['metric'], $item['group_median']) }}</div>
                                    <div class="text-[10px] text-base-content/40">n={{ $item['group_sample_size'] }}</div>
                                </div>
                                <div class="rounded-lg bg-base-200 p-2">
                                    <div class="text-[10px] text-base-content/40">Peers</div>
                                    <div class="mt-1 font-mono text-sm font-black">{{ $this->formatMetric($item['metric'], $item['peer_median']) }}</div>
                                    <div class="text-[10px] text-base-content/40">n={{ $item['peer_sample_size'] }}</div>
                                </div>
                                <div class="rounded-lg bg-base-200 p-2">
                                    <div class="text-[10px] text-base-content/40">Overall</div>
                                    <div class="mt-1 font-mono text-sm font-black">{{ $this->formatMetric($item['metric'], $item['overall_median']) }}</div>
                                    <div class="text-[10px] text-base-content/40">n={{ $item['overall_sample_size'] }}</div>
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-lg border border-base-300 px-3 py-2">
                                    <div class="text-[10px] text-base-content/40">Delta</div>
                                    <div class="mt-1 font-mono text-sm font-bold">{{ $this->formatDelta($item['metric'], $item['delta']) }}</div>
                                </div>
                                <div class="rounded-lg border border-base-300 px-3 py-2">
                                    <div class="text-[10px] text-base-content/40">Relative lift</div>
                                    <div class="mt-1 font-mono text-sm font-bold">{{ $this->formatLift($item['relative_lift']) }}</div>
                                </div>
                            </div>

                            <div class="mt-3 text-xs text-base-content/55">{{ $this->directionLabel($item['direction']) }}</div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-200">
                    <x-icon name="o-information-circle" class="size-5 text-base-content/50" />
                </div>
                <div>
                    <div class="text-sm font-black">چطور این صفحه را بخوانیم؟</div>
                    <p class="mt-1 text-sm leading-6 text-base-content/55">
                        Delta و lift توصیفی هستند. Above یا Below به‌تنهایی معنی بهتر یا بدتر نمی‌دهد؛ مثلاً پایین‌تر بودن Skip Rate می‌تواند مطلوب باشد، درحالی‌که برای Save Rate جهت مطلوب برعکس است. Usable فقط threshold نمونه‌ی V1 را رد کرده و به معنی significance یا causality نیست.
                    </p>
                </div>
            </div>
        </section>
    @endif
</div>