# Lumy — Analytics Dashboard V1

The Analytics Dashboard is the first product-facing read layer over Lumy's normalized factual data and Derived Metrics Layer.

## Purpose

The dashboard answers four operational questions without inventing missing data:

1. **Growth** — what follower and account-level signals do we currently have?
2. **Content** — how much of the content history has analytics and manual annotation coverage?
3. **Reels** — what do the latest Reel retention and skip signals look like?
4. **Audience** — what does the latest demographic snapshot show?

The dashboard is intentionally descriptive. It does not yet claim causal explanations or recommend future content. Those belong to the Content Intelligence phase.

## Account selection

`DashboardAnalytics` prefers the Zernio account configured by `ZERNIO_ACCOUNT_ID`. If no configured account is available locally, it falls back to the newest Instagram `social_accounts` row.

## Snapshot semantics

### Account metrics

The dashboard selects the latest account metric snapshot that contains account-insight fields such as `reach`, `views`, `accounts_engaged`, or `total_interactions`.

Follower-only rows are not treated as account-insight periods.

### Follower growth

The latest 14 account snapshots with a non-null `followers_count` are used for the Growth section. Ordering uses:

```text
COALESCE(provider_updated_at, captured_at)
```

The displayed follower delta is the difference between the latest two actual follower-count observations. With only a baseline snapshot, the delta remains `NULL` rather than becoming zero.

### Content analytics

Each content item uses exactly one `latestMetricSnapshot`. Rankings therefore never mix metrics from different snapshots of the same content.

Content analytics coverage is:

```text
contents with latest metric snapshot / all contents
```

Manual annotation coverage is:

```text
contents with content_annotation / all contents
```

### Reels

Advanced Reel coverage requires all of:

- average watch time
- positive video duration
- skip rate

Retention uses Lumy's canonical derived metric:

```text
avg_watch_time_ms / (video_duration_seconds × 1000)
```

The dashboard reports **median retention** and **median skip rate** across Reels with advanced metrics. Median is used so one unusually high or low Reel does not dominate the summary.

Skip rate remains in the provider's native percentage scale (`50.3` means `50.3%`). Retention remains a ratio internally (`0.503` means `50.3%`) and is formatted only in the UI.

## Audience

The latest snapshot is selected independently for each demographic dimension:

- age
- gender
- city
- country

Age and gender are treated as complete dimensions and percentages are calculated from their returned totals.

City and country remain explicitly **partial** because Zernio returns the top buckets rather than the full universe. They are ranked in the dashboard by `value DESC`; provider array order and `provider_position` are not interpreted as analytical rank.

Gender value `U` is preserved and displayed as Unknown/نامشخص rather than being silently removed from the denominator.

## V1 dashboard sections

- account KPI cards: followers, reach, views, total interactions, analytics coverage
- follower snapshot history
- content analytics and annotation coverage
- top content by views
- top content by High Intent Rate
- account interaction mix
- Reel advanced-metric coverage
- median Reel retention
- median Reel skip rate
- top Reels by views and retention
- age distribution
- gender distribution including Unknown
- top cities and countries by follower count

## Non-goals

The V1 dashboard does not yet provide:

- causal Content Intelligence
- Hook/Topic/Pillar performance aggregation
- experiment significance testing
- recommendation scoring
- AI-generated strategy
- cross-network analytics

Those layers should consume this same factual and derived metric foundation rather than introducing a second metric definition.
