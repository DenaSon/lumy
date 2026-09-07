# Lumy Derived Metrics Layer

Lumy derives its own content KPIs from normalized, factual `content_metric_snapshots` data. Derived values are calculated at read time and are not persisted as provider facts.

## Principles

- Raw provider metrics remain immutable historical observations.
- Derived metrics never overwrite provider data.
- Missing is not zero.
- A missing or zero denominator returns `NULL`.
- A required missing numerator returns `NULL` rather than assuming zero.
- Ratios are represented internally as decimal ratios, not percentages. `0.037` means `3.7%`.
- Presentation rounding belongs to the UI, not the analytics layer.

## Metrics

| Metric | Formula | Notes |
| --- | --- | --- |
| `interaction_count` | `likes + comments + shares + saves` | Requires all four inputs. |
| `high_intent_actions` | `shares + saves` | Stronger intent than passive engagement. |
| `like_rate` | `likes / views` | Per-view ratio. |
| `comment_rate` | `comments / views` | Per-view ratio. |
| `share_rate` | `shares / views` | Per-view ratio. |
| `save_rate` | `saves / views` | Per-view ratio. |
| `high_intent_rate` | `(shares + saves) / views` | Primary utility/intent signal. |
| `engagement_per_view` | `(likes + comments + shares + saves) / views` | Lumy's explicit engagement definition. |
| `engagement_per_reach` | `(likes + comments + shares + saves) / reach` | Separates interaction depth from distribution. |
| `retention_rate` | `avg_watch_time_ms / (video_duration_seconds * 1000)` | Reel watch-time proxy, not a retention curve. |

## Interaction Definition

Lumy intentionally excludes `follows` and `reposts` from `interaction_count`.

- `follows` is unavailable for some Instagram content, including observed Reel analytics, so including it would make the metric inconsistent across content.
- `reposts` is retained as a raw provider metric but is not currently reliable enough across Instagram connection modes to be part of Lumy's canonical engagement formula.

The provider's `provider_engagement_rate` remains stored for audit/comparison, but Lumy does not use it as the canonical engagement KPI.

## Retention Semantics

`retention_rate` is an average-watch-time ratio:

```text
avg_watch_time_ms / video_duration_ms
```

It is a useful comparable proxy for how much of a Reel is watched on average, but it is not an audience-retention curve and should not be described as one.

The value is not clamped to `1.0`. Replays or looping can legitimately make average watch time exceed the nominal video duration.

## Example

For the validated Lumixo Reel snapshot:

```text
views              167106
reach              130225
likes                 2494
comments              1345
shares                4116
saves                 6197
avg_watch_time_ms     8784
video_duration_s        28
```

Lumy derives:

```text
interaction_count     14152
high_intent_actions   10313
engagement_per_view   ~0.0847
retention_rate        ~0.3137
```

The exact values remain unrounded inside the analytics layer.

## Application Interface

For a snapshot:

```php
$metrics = $snapshot->derivedMetrics();
$metrics->saveRate;
$metrics->retentionRate;
$metrics->toArray();
```

For a content item, `latestMetricSnapshot` selects the observation with the latest provider update timestamp. This is the default factual snapshot to use for current-state catalogs and dashboards.

## Future Extensions

This layer is intentionally limited to deterministic metrics that can be derived from one snapshot. Later analytics work may add:

- age-normalized performance checkpoints (`24h`, `72h`, `7d`, `30d`, `90d`)
- growth velocity
- decay curves
- percentile/rank models inside comparable cohorts
- goal-specific composite scores

Those should build on these canonical metrics rather than redefining them.
