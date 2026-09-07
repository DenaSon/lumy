# Content Intelligence Aggregation

Lumy's Content Intelligence aggregation layer converts one account's annotated content history into read-only comparable groups. It does not persist aggregates, scores, claims, or recommendations.

## Scope

`App\ContentIntelligence\ContentIntelligenceAnalytics` analyzes exactly one `SocialAccount` at a time. This prevents content from different Instagram accounts from being mixed into one pattern.

Supported V1 dimensions:

- Primary Hook Type
- Primary Hook Source
- Primary Topic
- Primary Pillar
- Goal
- CTA Type
- Production Style

## Attribution rules

Primary attribution is intentionally strict.

- Hook analysis uses only the Hook marked `is_primary = true`.
- Topic analysis uses only the `content_topic` row marked `is_primary = true`.
- Pillar analysis uses `content_annotations.primary_pillar_id`.
- Goal, CTA Type, and Production Style use the single `content_annotations` row.
- Secondary Hooks and secondary Topics do not contribute to the primary aggregation.
- A Content row can contribute at most once to a given dimension group.

These rules avoid inflating sample sizes when one post contains multiple Hooks or Topics.

## Snapshot rule

Every content contributes metrics from one factual observation only:

`Content::latestMetricSnapshot`

Older snapshots are not combined with newer snapshots. Metrics from different snapshots are never mixed into one content observation.

## Metrics

Each group reports a median and an independent metric sample size for:

- Views
- Reach
- Like Rate
- Comment Rate
- Share Rate
- Save Rate
- High Intent Rate
- Engagement per View
- Engagement per Reach
- Retention Rate
- Skip Rate

Derived rates retain the semantics of `ContentDerivedMetrics`: required missing inputs stay `NULL`, zero denominators produce `NULL`, and a factual zero numerator remains a real `0` observation.

`skip_rate` is the provider percentage scale (for example `50.3` means `50.3%`). Lumy-derived rates are ratios (for example `0.503` means `50.3%`).

## Sample sizes

A group has two top-level sample counts:

- `sample_size`: attributed Content rows, whether or not Analytics exists.
- `analytics_sample_size`: attributed Content rows with a latest metric snapshot.

Every metric also has its own `sample_size`. A Content row with a snapshot but a missing metric is excluded only from that metric's sample; it is not converted to zero.

Sample status is deliberately simple in V1:

| Sample size | Status | Meaning |
| ---: | --- | --- |
| `< 3` | `insufficient` | Do not treat as a usable pattern. |
| `3–4` | `exploratory` | Directional only; evidence is still thin. |
| `>= 5` | `usable` | Large enough to surface as a pattern candidate, not proof of causality. |

The status is calculated both for the group and independently for every metric because Retention or Skip Rate may have fewer observations than Views.

## Median, not average

Group summaries use the median. Content performance is typically skewed by a small number of unusually large posts, so median is more robust than arithmetic mean for V1 pattern discovery.

For an even number of observations, the median is the arithmetic midpoint of the two central values.

## Lifetime scale metrics

Views and Reach currently come from lifetime content snapshots. Older posts have had more time to accumulate scale, so these metrics are labeled:

`comparison_role = descriptive_lifetime`

They are useful context but should not drive future cross-content performance claims until Lumy has comparable age-based checkpoints such as D1, D3, D7, D30, and D90.

Behavior metrics such as Save Rate, Share Rate, High Intent Rate, Retention, and Skip Rate are labeled:

`comparison_role = behavior`

This classification does not itself imply statistical significance or causality.

## Explicit non-goals of this layer

This layer does not yet:

- compare a group with an overall baseline;
- calculate lift or percentage-point deltas;
- generate evidence-backed natural-language insights;
- run significance tests;
- control for content age or confounders;
- assign a universal content score;
- produce recommendations.

Those belong to the subsequent evidence/baseline layer.
