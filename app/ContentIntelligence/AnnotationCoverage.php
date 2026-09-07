<?php

namespace App\ContentIntelligence;

use App\Models\Content;

class AnnotationCoverage
{
    /** @return array<string, int|float|null> */
    public function summary(): array
    {
        $total = Content::query()->count();

        $counts = [
            'with_metrics' => Content::query()->whereHas('metricSnapshots')->count(),
            'annotated' => Content::query()->whereHas('annotation')->count(),
            'with_primary_pillar' => Content::query()
                ->whereHas('annotation', fn ($query) => $query->whereNotNull('primary_pillar_id'))
                ->count(),
            'with_primary_topic' => Content::query()
                ->whereHas('topics', fn ($query) => $query->where('content_topic.is_primary', true))
                ->count(),
            'with_primary_hook' => Content::query()
                ->whereHas('hooks', fn ($query) => $query->where('is_primary', true))
                ->count(),
            'with_hook_type' => Content::query()
                ->whereHas('hooks', fn ($query) => $query->whereNotNull('type')->where('type', '!=', ''))
                ->count(),
            'with_hook_source' => Content::query()
                ->whereHas('hooks', fn ($query) => $query->whereNotNull('source')->where('source', '!=', ''))
                ->count(),
            'with_goal' => Content::query()
                ->whereHas('annotation', fn ($query) => $query->whereNotNull('goal')->where('goal', '!=', ''))
                ->count(),
        ];

        return [
            'total' => $total,
            ...$counts,
            'annotation_rate' => $this->rate($counts['annotated'], $total),
            'primary_pillar_rate' => $this->rate($counts['with_primary_pillar'], $total),
            'primary_topic_rate' => $this->rate($counts['with_primary_topic'], $total),
            'primary_hook_rate' => $this->rate($counts['with_primary_hook'], $total),
        ];
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : $numerator / $denominator;
    }
}
