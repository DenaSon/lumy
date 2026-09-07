<?php

namespace App\ContentIntelligence;

use App\Models\SocialAccount;
use Illuminate\Support\Collection;

/**
 * Convert analytical comparisons into structured evidence candidates.
 *
 * This layer remains deterministic and read-only. It does not generate natural
 * language, significance claims, causal claims, scores, or recommendations.
 */
final class ContentIntelligenceEvidence
{
    public function __construct(
        private readonly ContentIntelligenceAnalytics $analytics,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forAccount(SocialAccount $account): Collection
    {
        return collect(ContentIntelligenceAnalytics::DIMENSIONS)
            ->flatMap(fn (string $dimension) => $this->dimension($account, $dimension))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function dimension(SocialAccount $account, string $dimension): Collection
    {
        $analysis = $this->analytics->analyzeDimension($account, $dimension);

        return $analysis['groups']
            ->flatMap(function (array $group) use ($analysis) {
                return collect($group['metrics'])
                    ->map(function (array $metric, string $metricName) use ($group, $analysis) {
                        if ($metric['comparison_role'] !== 'behavior') {
                            return null;
                        }

                        $comparison = $group['comparisons'][$metricName];

                        if ($metric['median'] === null || $comparison['peer_median'] === null) {
                            return null;
                        }

                        return [
                            'dimension' => $group['dimension'],
                            'group_key' => $group['key'],
                            'group_label' => $group['label'],
                            'group_meta' => $group['meta'],
                            'metric' => $metricName,
                            'kind' => $metric['kind'],
                            'comparison_role' => $metric['comparison_role'],
                            'group_median' => $metric['median'],
                            'group_sample_size' => $metric['sample_size'],
                            'group_sample_status' => $metric['sample_status'],
                            'peer_median' => $comparison['peer_median'],
                            'peer_sample_size' => $comparison['peer_sample_size'],
                            'peer_sample_status' => $comparison['peer_sample_status'],
                            'overall_median' => $analysis['baseline'][$metricName]['median'],
                            'overall_sample_size' => $analysis['baseline'][$metricName]['sample_size'],
                            'delta' => $comparison['delta'],
                            'relative_lift' => $comparison['relative_lift'],
                            'direction' => $comparison['direction'],
                            'evidence_status' => $comparison['evidence_status'],
                            'eligible' => $comparison['evidence_status'] !== 'insufficient'
                                && $comparison['direction'] !== 'same',
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->values();
    }
}
