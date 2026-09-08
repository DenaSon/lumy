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

    public function mount(): void
    {
        if (! array_key_exists($this->dimension, $this->dimensionOptions())) {
            $this->dimension = 'primary_hook_type';
        }
    }

    public function updatedDimension(): void
    {
        if (! array_key_exists($this->dimension, $this->dimensionOptions())) {
            $this->dimension = 'primary_hook_type';
        }

        unset($this->analysis, $this->evidence);
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
            <h1 class="mt-1 text-2xl font-black lg:text-3xl">الگوهای عملکرد محتوا</h1>
            <p class="mt-2 max-w-4xl text-sm text-base-content/60">
                مقایسه‌ی deterministic بین Content DNA و performance واقعی Lumixo. این صفحه pattern و evidence نشان می‌دهد؛ نه causal claim و نه recommendation خودکار.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a wire:navigate href="{{ route('intelligence.index') }}" class="btn btn-outline btn-sm">Taxonomy & Coverage</a>
            <a wire:navigate href="{{ route('content.index') }}" class="btn btn-ghost btn-sm">Content Catalog</a>
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

        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-sm font-black">{{ '@'.$this->account->username }}</div>
                    <div class="mt-1 text-xs text-base-content/50">
                        {{ number_format($analysis['attributed_sample_size']) }} محتوای دارای attribution برای {{ $this->dimensionOptions()[$dimension] }}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->dimensionOptions() as $key => $label)
                        <button
                            type="button"
                            wire:click="$set('dimension', '{{ $key }}')"
                            class="btn btn-sm {{ $dimension === $key ? 'btn-primary' : 'btn-ghost' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-black">Overall baseline</h2>
                    <p class="text-sm text-base-content/55">Median کل محتوای attributed در همین dimension؛ baseline شامل همه‌ی گروه‌هاست.</p>
                </div>
                <div class="badge badge-outline">n={{ number_format($analysis['attributed_sample_size']) }}</div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach($this->primaryMetrics() as $metric)
                    @php($item = $baseline[$metric])
                    <div class="rounded-xl bg-base-200 p-4">
                        <div class="text-xs text-base-content/55">{{ $this->metricLabels()[$metric] }}</div>
                        <div class="mt-1 text-xl font-black">{{ $this->formatMetric($metric, $item['median']) }}</div>
                        <div class="mt-1 text-[11px] text-base-content/45">n={{ $item['sample_size'] }} · {{ $this->statusLabel($item['sample_status']) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-sm">
            <div class="border-b border-base-300 px-5 py-4">
                <h2 class="text-lg font-black">Group vs peer baseline</h2>
                <p class="text-sm text-base-content/55">
                    Peer baseline هر ردیف، همان dimension بدون گروه فعلی است؛ بنابراین گروه هیچ‌وقت با baseline شامل خودش مقایسه نمی‌شود.
                </p>
            </div>

            @if($groups->isEmpty())
                <div class="p-8 text-center text-sm text-base-content/55">برای این dimension هنوز attribution کافی وجود ندارد.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                        <tr>
                            <th>Group</th>
                            <th>Sample</th>
                            @foreach($this->primaryMetrics() as $metric)
                                <th class="min-w-40">{{ $this->metricLabels()[$metric] }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($groups as $group)
                            <tr wire:key="intelligence-group-{{ $dimension }}-{{ $group['key'] }}">
                                <td>
                                    <div class="font-bold">{{ $group['label'] }}</div>
                                    @if(($group['meta']['pillar_name'] ?? null) !== null)
                                        <div class="text-[11px] text-base-content/45">{{ $group['meta']['pillar_name'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-mono font-bold">n={{ $group['sample_size'] }}</div>
                                    <div class="mt-1 text-[11px] text-base-content/45">{{ $this->statusLabel($group['sample_status']) }}</div>
                                </td>
                                @foreach($this->primaryMetrics() as $metric)
                                    @php($metricRow = $group['metrics'][$metric])
                                    @php($comparison = $group['comparisons'][$metric])
                                    <td>
                                        <div class="font-mono font-bold">{{ $this->formatMetric($metric, $metricRow['median']) }}</div>
                                        <div class="mt-1 text-[11px] text-base-content/50">peer {{ $this->formatMetric($metric, $comparison['peer_median']) }}</div>
                                        <div class="text-[11px] text-base-content/50">
                                            Δ {{ $this->formatDelta($metric, $comparison['delta']) }} · n={{ $metricRow['sample_size'] }}/{{ $comparison['peer_sample_size'] }}
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-black">Evidence candidates</h2>
                    <p class="text-sm text-base-content/55">
                        فقط behavior metricها. Views و Reach تا قبل از age-normalized checkpoints به‌عنوان evidence استفاده نمی‌شوند.
                    </p>
                </div>
                <div class="text-xs text-base-content/45">Eligible یعنی sample کافی برای surfacing دارد؛ نه اثبات causality یا significance.</div>
            </div>

            @if($this->evidence->isEmpty())
                <div class="mt-4 rounded-xl bg-base-200 p-6 text-center text-sm text-base-content/55">Evidence قابل مقایسه‌ای برای این dimension وجود ندارد.</div>
            @else
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->evidence->take(12) as $item)
                        <article class="rounded-xl border border-base-300 p-4" wire:key="evidence-{{ $dimension }}-{{ $item['group_key'] }}-{{ $item['metric'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs text-base-content/50">{{ $this->metricLabels()[$item['metric']] }}</div>
                                    <h3 class="mt-1 font-black">{{ $item['group_label'] }}</h3>
                                </div>
                                <span class="badge badge-sm {{ $item['eligible'] ? 'badge-primary' : 'badge-outline' }}">{{ $this->statusLabel($item['evidence_status']) }}</span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-base-200 p-2">
                                    <div class="text-[10px] text-base-content/45">Group</div>
                                    <div class="mt-1 font-mono text-sm font-bold">{{ $this->formatMetric($item['metric'], $item['group_median']) }}</div>
                                    <div class="text-[10px] text-base-content/40">n={{ $item['group_sample_size'] }}</div>
                                </div>
                                <div class="rounded-lg bg-base-200 p-2">
                                    <div class="text-[10px] text-base-content/45">Peers</div>
                                    <div class="mt-1 font-mono text-sm font-bold">{{ $this->formatMetric($item['metric'], $item['peer_median']) }}</div>
                                    <div class="text-[10px] text-base-content/40">n={{ $item['peer_sample_size'] }}</div>
                                </div>
                                <div class="rounded-lg bg-base-200 p-2">
                                    <div class="text-[10px] text-base-content/45">Overall</div>
                                    <div class="mt-1 font-mono text-sm font-bold">{{ $this->formatMetric($item['metric'], $item['overall_median']) }}</div>
                                    <div class="text-[10px] text-base-content/40">n={{ $item['overall_sample_size'] }}</div>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                <span class="badge badge-outline">Δ {{ $this->formatDelta($item['metric'], $item['delta']) }}</span>
                                <span class="badge badge-outline">Lift {{ $this->formatLift($item['relative_lift']) }}</span>
                                <span class="text-base-content/55">{{ $this->directionLabel($item['direction']) }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-base-300 bg-base-100 p-4 text-sm text-base-content/55 shadow-sm">
            <strong class="text-base-content">Interpretation guardrail:</strong>
            delta و lift در این صفحه descriptive هستند. Usable فقط threshold نمونه‌ی V1 را رد کرده است؛ کنترل confounder، significance test و causal inference هنوز انجام نمی‌شود.
        </section>
    @endif
</div>