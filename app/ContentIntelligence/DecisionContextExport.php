<?php

namespace App\ContentIntelligence;

use App\Analytics\DashboardAnalytics;
use App\Models\Content;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Build a deterministic decision context from the same analytical services
 * that power Lumy's Dashboard and Content Intelligence UI.
 */
final class DecisionContextExport
{
    public function __construct(
        private readonly DashboardAnalytics $dashboard,
        private readonly ContentIntelligenceAnalytics $analytics,
        private readonly ContentIntelligenceEvidence $evidence,
    ) {}

    /** @return array<string, mixed> */
    public function build(?SocialAccount $account = null): array
    {
        $account ??= $this->dashboard->defaultAccount();

        if ($account === null) {
            return [
                'meta' => $this->meta(),
                'account' => null,
                'data_quality' => $this->dataQuality(),
                'performance_baseline' => null,
                'audience' => null,
                'top_content' => [],
                'intelligence' => [],
            ];
        }

        $dashboard = $this->dashboard->build($account);
        $formatAnalysis = $this->analytics->analyzeDimension($account, 'format');
        $evidence = $this->evidence
            ->forAccount($account)
            ->filter(fn (array $item) => (bool) $item['eligible'])
            ->groupBy('dimension');

        return [
            'meta' => $this->meta(),
            'account' => $this->accountSummary($dashboard),
            'data_quality' => $this->dataQuality(),
            'performance_baseline' => $this->performanceBaseline($formatAnalysis),
            'audience' => $this->audience($dashboard['audience']),
            'top_content' => [
                'by_views' => $this->serializeTopContents($dashboard['content']['top_views']),
                'by_high_intent' => $this->serializeTopContents($dashboard['content']['top_high_intent']),
            ],
            'intelligence' => collect(ContentIntelligenceAnalytics::DIMENSIONS)
                ->mapWithKeys(function (string $dimension) use ($evidence) {
                    $items = $evidence->get($dimension, collect());

                    return [$dimension => [
                        'evidence_count' => $items->count(),
                        'evidence' => $items
                            ->map(fn (array $item) => $this->serializeEvidence($item))
                            ->values()
                            ->all(),
                    ]];
                })
                ->all(),
        ];
    }

    /** @param array<string, mixed> $context */
    public function toJson(array $context): string
    {
        return json_encode(
            $context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        )."\n";
    }

    /** @param array<string, mixed> $context */
    public function toMarkdown(array $context): string
    {
        if ($context['account'] === null) {
            return "# Lumy Decision Context\n\nNo Instagram account is available.\n";
        }

        $account = $context['account'];
        $baseline = $context['performance_baseline'];
        $audience = $context['audience'];
        $lines = [
            '# Lumy Decision Context',
            '',
            '> Deterministic export for manual AI analysis. Treat this as evidence, not causal proof.',
            '',
            '## Account',
            '',
            '- Account: @'.($account['username'] ?? 'unknown'),
            '- Generated at: '.$context['meta']['generated_at'],
            '- Followers: '.$this->number($account['followers_count']),
            '- Content: '.$this->number($account['content_count']),
            '- Analytics coverage: '.$this->percent($account['analytics_coverage']),
            '- Annotation coverage: '.$this->percent($account['annotation_coverage']),
            '- Primary Hook coverage: '.$this->percent($account['primary_hook_coverage']),
            '',
            '## Account Insights',
            '',
            '- Period: '.($account['account_insights']['period_start'] ?? '—').' → '.($account['account_insights']['period_end'] ?? '—'),
            '- Reach: '.$this->number($account['account_insights']['reach']),
            '- Views: '.$this->number($account['account_insights']['views']),
            '- Accounts engaged: '.$this->number($account['account_insights']['accounts_engaged']),
            '- Total interactions: '.$this->number($account['account_insights']['total_interactions']),
            '',
            '## Performance Baseline',
            '',
            'Baseline basis: attributed content in the `format` dimension. Each metric keeps its own sample size.',
            '',
            '| Metric | Median | n | Status |',
            '| --- | ---: | ---: | --- |',
        ];

        foreach ($baseline['metrics'] as $metric => $item) {
            $lines[] = '| '.$this->metricLabel($metric).' | '.$this->metric($metric, $item['median']).' | '.$item['sample_size'].' | '.$item['sample_status'].' |';
        }

        $lines[] = '';
        $lines[] = '## Audience Summary';
        $lines[] = '';
        $lines[] = '### Age';
        $lines[] = '';

        foreach ($audience['age'] as $row) {
            $lines[] = '- '.$row['dimension'].': '.$this->percent($row['share']);
        }

        $lines[] = '';
        $lines[] = '### Gender';
        $lines[] = '';

        foreach ($audience['gender'] as $row) {
            $lines[] = '- '.$row['dimension'].': '.$this->percent($row['share']);
        }

        $lines[] = '';
        $lines[] = '### Top locations';
        $lines[] = '';
        $lines[] = '- Cities: '.collect($audience['cities'])->map(fn (array $row) => $row['dimension'].' ('.$this->number($row['value']).')')->implode(', ');
        $lines[] = '- Countries: '.collect($audience['countries'])->map(fn (array $row) => $row['dimension'].' ('.$this->number($row['value']).')')->implode(', ');

        foreach (['by_views' => 'Top Content by Views', 'by_high_intent' => 'Top Content by High Intent'] as $key => $title) {
            $lines[] = '';
            $lines[] = '## '.$title;
            $lines[] = '';

            foreach ($context['top_content'][$key] as $content) {
                $lines = [...$lines, ...$this->contentMarkdown($content)];
            }
        }

        $lines[] = '## Intelligence Evidence';
        $lines[] = '';
        $lines[] = 'Only eligible evidence is exported. Insufficient, same, and missing comparisons are omitted.';

        foreach ($context['intelligence'] as $dimension => $section) {
            $lines[] = '';
            $lines[] = '### '.$dimension;
            $lines[] = '';

            if ($section['evidence'] === []) {
                $lines[] = '_No eligible evidence._';

                continue;
            }

            $lines[] = '| Evidence ID | Group | Metric | Group | Peers | Delta | Lift | n group/peer | Status | Direction |';
            $lines[] = '| --- | --- | --- | ---: | ---: | ---: | ---: | ---: | --- | --- |';

            foreach ($section['evidence'] as $item) {
                $lines[] = '| `'.$item['evidence_id'].'` | '.$item['group_label'].' | '.$this->metricLabel($item['metric']).' | '.$this->metric($item['metric'], $item['group_median']).' | '.$this->metric($item['metric'], $item['peer_median']).' | '.$this->delta($item['metric'], $item['delta']).' | '.$this->lift($item['relative_lift']).' | '.$item['group_sample_size'].'/'.$item['peer_sample_size'].' | '.$item['evidence_status'].' | '.$item['direction'].' |';
            }
        }

        $lines = [
            ...$lines,
            '',
            '## Interpretation Guardrails',
            '',
            '- `NULL` means missing/unknown and must never be treated as zero.',
            '- Views and Reach are lifetime scale metrics and may be age-biased across old vs new content.',
            '- No statistical significance testing has been performed.',
            '- No causal inference has been performed.',
            '- Sample status: `<3 = insufficient`, `3–4 = exploratory`, `>=5 = usable`.',
            '- Ratio metrics are stored as 0–1. Skip Rate is stored as provider percent 0–100.',
            '- Any recommendation made from this export should cite one or more `evidence_id` values and distinguish exploratory evidence from usable evidence.',
            '',
        ];

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $context */
    public function filename(array $context, string $format): string
    {
        $username = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) ($context['account']['username'] ?? 'instagram')) ?: 'instagram';
        $extension = $format === 'markdown' ? 'md' : 'json';

        return 'lumy-decision-context-'.$username.'-'.now()->utc()->format('Ymd-His').'.'.$extension;
    }

    /** @return array<string, mixed> */
    private function meta(): array
    {
        return [
            'schema' => 'lumy.decision_context.v1',
            'generated_at' => now()->utc()->toIso8601String(),
            'source' => 'Lumy deterministic analytics',
            'purpose' => 'Manual decision support and AI context transfer',
        ];
    }

    /** @return array<string, mixed> */
    private function dataQuality(): array
    {
        return [
            'missing_values' => 'NULL is unknown/missing and is never equivalent to zero.',
            'snapshot_semantics' => 'Content performance uses each content\'s single latest factual snapshot.',
            'views_reach_caveat' => 'Views and Reach are lifetime scale metrics and may be age-biased until checkpoint cohorts are available.',
            'statistical_significance_tested' => false,
            'causal_inference_performed' => false,
            'sample_status_thresholds' => [
                'insufficient' => 'n < 3',
                'exploratory' => 'n = 3–4',
                'usable' => 'n >= 5',
            ],
            'metric_units' => [
                'ratio' => '0–1',
                'provider_percent' => '0–100',
                'count' => 'absolute count',
            ],
            'evidence_export_policy' => 'Only eligible evidence is included; insufficient, same, and missing comparisons are omitted.',
            'recommendation_guardrail' => 'Any recommendation should cite evidence_id values and distinguish exploratory from usable evidence.',
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function accountSummary(array $dashboard): array
    {
        $account = $dashboard['account'];
        $insight = $dashboard['account_insight'];

        return [
            'id' => $account->id,
            'username' => $account->username,
            'followers_count' => $dashboard['growth']['followers_count'],
            'content_count' => $dashboard['content']['total'],
            'analytics_coverage' => $dashboard['content']['analytics_coverage'],
            'annotation_coverage' => $dashboard['content']['annotation_coverage'],
            'primary_hook_coverage' => $dashboard['content']['primary_hook_coverage'],
            'account_insights' => [
                'period_start' => $insight?->period_start?->utc()->toIso8601String(),
                'period_end' => $insight?->period_end?->utc()->toIso8601String(),
                'reach' => $insight?->reach,
                'views' => $insight?->views,
                'accounts_engaged' => $insight?->accounts_engaged,
                'total_interactions' => $insight?->total_interactions,
                'likes' => $insight?->likes,
                'comments' => $insight?->comments,
                'saves' => $insight?->saves,
                'shares' => $insight?->shares,
                'reposts' => $insight?->reposts,
                'follows_and_unfollows' => $insight?->follows_and_unfollows,
                'profile_links_taps' => $insight?->profile_links_taps,
                'captured_at' => $insight?->captured_at?->utc()->toIso8601String(),
            ],
        ];
    }

    /** @param array<string, mixed> $analysis */
    private function performanceBaseline(array $analysis): array
    {
        $metrics = ['high_intent_rate', 'save_rate', 'share_rate', 'retention_rate', 'skip_rate'];

        return [
            'basis_dimension' => 'format',
            'attributed_sample_size' => $analysis['attributed_sample_size'],
            'metrics' => collect($metrics)
                ->mapWithKeys(fn (string $metric) => [$metric => $analysis['baseline'][$metric]])
                ->all(),
        ];
    }

    /** @param array<string, mixed> $audience */
    private function audience(array $audience): array
    {
        return [
            'captured_at' => $this->date($audience['captured_at']),
            'age' => $audience['age']->map(fn (array $item) => [
                'dimension' => $item['row']->dimension,
                'value' => $item['row']->value,
                'share' => $item['share'],
            ])->values()->all(),
            'gender' => $audience['gender']->map(fn (array $item) => [
                'dimension' => $item['row']->dimension,
                'value' => $item['row']->value,
                'share' => $item['share'],
            ])->values()->all(),
            'cities' => $audience['cities']->map(fn ($row) => [
                'dimension' => $row->dimension,
                'value' => $row->value,
            ])->values()->all(),
            'countries' => $audience['countries']->map(fn ($row) => [
                'dimension' => $row->dimension,
                'value' => $row->value,
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array{content: Content, value: int|float, derived: mixed}>  $items
     * @return array<int, array<string, mixed>>
     */
    private function serializeTopContents(Collection $items): array
    {
        return $items
            ->map(fn (array $item) => $this->serializeContent($item['content']))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function serializeContent(Content $content): array
    {
        $content->loadMissing([
            'annotation.primaryPillar',
            'topics.pillar',
            'hooks',
            'latestMetricSnapshot',
        ]);

        $snapshot = $content->latestMetricSnapshot;
        $derived = $snapshot?->derivedMetrics();
        $annotation = $content->annotation;
        $primaryHook = $content->hooks->first(fn ($hook) => (bool) $hook->is_primary);
        $primaryTopic = $content->topics->first(fn ($topic) => (bool) $topic->pivot?->is_primary);

        return [
            'id' => $content->id,
            'platform_post_id' => $content->platform_post_id,
            'permalink' => $content->permalink,
            'caption' => $content->caption,
            'content_type' => $content->content_type,
            'published_at' => $content->published_at?->utc()->toIso8601String(),
            'metrics' => [
                'views' => $snapshot?->views,
                'reach' => $snapshot?->reach,
                'likes' => $snapshot?->likes,
                'comments' => $snapshot?->comments,
                'shares' => $snapshot?->shares,
                'saves' => $snapshot?->saves,
                'like_rate' => $derived?->likeRate,
                'comment_rate' => $derived?->commentRate,
                'share_rate' => $derived?->shareRate,
                'save_rate' => $derived?->saveRate,
                'high_intent_rate' => $derived?->highIntentRate,
                'engagement_per_view' => $derived?->engagementPerView,
                'engagement_per_reach' => $derived?->engagementPerReach,
                'retention_rate' => $derived?->retentionRate,
                'skip_rate' => $snapshot?->skip_rate,
                'provider_updated_at' => $snapshot?->provider_updated_at?->utc()->toIso8601String(),
            ],
            'content_dna' => [
                'primary_pillar' => $annotation?->primaryPillar === null ? null : [
                    'id' => $annotation->primaryPillar->id,
                    'name' => $annotation->primaryPillar->name,
                    'slug' => $annotation->primaryPillar->slug,
                ],
                'primary_topic' => $primaryTopic === null ? null : [
                    'id' => $primaryTopic->id,
                    'name' => $primaryTopic->name,
                    'slug' => $primaryTopic->slug,
                    'pillar' => $primaryTopic->pillar?->name,
                ],
                'primary_hook' => $primaryHook === null ? null : [
                    'text' => $primaryHook->text,
                    'type' => $primaryHook->type,
                    'source' => $primaryHook->source,
                ],
                'goal' => $annotation?->goal,
                'cta_type' => $annotation?->cta_type,
                'target_audience' => $annotation?->target_audience,
                'production_style' => $annotation?->production_style,
                'cover_style' => $annotation?->cover_style,
            ],
        ];
    }

    /** @param array<string, mixed> $item */
    private function serializeEvidence(array $item): array
    {
        return [
            'evidence_id' => $item['dimension'].':'.$item['group_key'].':'.$item['metric'],
            'dimension' => $item['dimension'],
            'group_key' => $item['group_key'],
            'group_label' => $item['group_label'],
            'group_meta' => $item['group_meta'],
            'metric' => $item['metric'],
            'kind' => $item['kind'],
            'group_median' => $item['group_median'],
            'group_sample_size' => $item['group_sample_size'],
            'group_sample_status' => $item['group_sample_status'],
            'peer_median' => $item['peer_median'],
            'peer_sample_size' => $item['peer_sample_size'],
            'peer_sample_status' => $item['peer_sample_status'],
            'overall_median' => $item['overall_median'],
            'overall_sample_size' => $item['overall_sample_size'],
            'delta' => $item['delta'],
            'relative_lift' => $item['relative_lift'],
            'direction' => $item['direction'],
            'evidence_status' => $item['evidence_status'],
        ];
    }

    /** @param array<string, mixed> $content */
    private function contentMarkdown(array $content): array
    {
        return [
            '### Content #'.$content['id'].' · '.($content['content_type'] ?? 'unknown'),
            '',
            '- Caption: '.str_replace(["\r", "\n"], ' ', (string) ($content['caption'] ?? '')),
            '- Published: '.($content['published_at'] ?? '—'),
            '- Views: '.$this->number($content['metrics']['views']),
            '- Reach: '.$this->number($content['metrics']['reach']),
            '- Save Rate: '.$this->percent($content['metrics']['save_rate']),
            '- Share Rate: '.$this->percent($content['metrics']['share_rate']),
            '- High Intent: '.$this->percent($content['metrics']['high_intent_rate']),
            '- Retention: '.$this->percent($content['metrics']['retention_rate']),
            '- Skip Rate: '.$this->providerPercent($content['metrics']['skip_rate']),
            '- Pillar: '.($content['content_dna']['primary_pillar']['name'] ?? '—'),
            '- Topic: '.($content['content_dna']['primary_topic']['name'] ?? '—'),
            '- Hook: '.($content['content_dna']['primary_hook']['text'] ?? '—'),
            '- Hook Type: '.($content['content_dna']['primary_hook']['type'] ?? '—'),
            '- Hook Source: '.($content['content_dna']['primary_hook']['source'] ?? '—'),
            '- Goal: '.($content['content_dna']['goal'] ?? '—'),
            '- CTA: '.($content['content_dna']['cta_type'] ?? '—'),
            '- Production Style: '.($content['content_dna']['production_style'] ?? '—'),
            '',
        ];
    }

    private function metricLabel(string $metric): string
    {
        return match ($metric) {
            'high_intent_rate' => 'High Intent',
            'save_rate' => 'Save Rate',
            'share_rate' => 'Share Rate',
            'retention_rate' => 'Retention',
            'skip_rate' => 'Skip Rate',
            'like_rate' => 'Like Rate',
            'comment_rate' => 'Comment Rate',
            'engagement_per_view' => 'Engagement / View',
            'engagement_per_reach' => 'Engagement / Reach',
            default => $metric,
        };
    }

    private function number(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value) ? '—' : number_format((float) $value, 0);
    }

    private function percent(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value) ? '—' : number_format((float) $value * 100, 1).'%';
    }

    private function providerPercent(int|float|string|null $value): string
    {
        return $value === null || ! is_numeric($value) ? '—' : number_format((float) $value, 1).'%';
    }

    private function metric(string $metric, int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return $metric === 'skip_rate'
            ? $this->providerPercent($value)
            : $this->percent($value);
    }

    private function delta(string $metric, int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = $metric === 'skip_rate' ? (float) $value : (float) $value * 100;

        return ($formatted > 0 ? '+' : '').number_format($formatted, 1).' pp';
    }

    private function lift(int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $percent = (float) $value * 100;

        return ($percent > 0 ? '+' : '').number_format($percent, 1).'%';
    }

    private function date(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->utc()->toIso8601String();
    }
}
