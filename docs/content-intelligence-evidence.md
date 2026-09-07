# Content Intelligence Evidence

`App\ContentIntelligence\ContentIntelligenceEvidence` turns Content Intelligence comparisons into deterministic, structured evidence candidates. It is a read-only bridge between Analytics and the future Intelligence UI / AI strategist.

## Purpose

The layer answers a narrow question:

> For an annotated content group, how does a behavioral metric differ from the other content attributed to the same dimension, and how much evidence supports that contrast?

It does not decide what Lumixo should publish next.

## Inputs

Evidence is built from `ContentIntelligenceAnalytics::analyzeDimension()`.

That means it inherits these rules:

- one Social Account at a time;
- primary Hook / Topic / Pillar attribution only;
- one latest factual metric snapshot per content;
- missing values stay missing;
- real zero values remain observations;
- medians instead of averages.

## Peer baseline

Evidence uses the group-vs-peer comparison, not the inclusive overall median.

For a Hook Type such as `curiosity`, the peer cohort is every other content with a Primary Hook Type in the same Social Account, excluding `curiosity` content itself.

This makes the contrast explicit:

`group median - peer median`

The overall dimension median is still included as context in each evidence record, but it is not the comparison denominator for delta or relative lift.

## Evidence record

Each structured record includes:

- dimension;
- group key, label, and metadata;
- metric name and kind;
- group median and metric sample size;
- peer median and metric sample size;
- overall dimension median and sample size;
- absolute delta;
- relative lift when defined;
- direction: `above`, `below`, or `same`;
- evidence status;
- `eligible` flag.

## Evidence status

The comparison uses the weaker metric sample between the group and its peers:

| Minimum of group/peer metric N | Status |
| ---: | --- |
| `< 3` | `insufficient` |
| `3–4` | `exploratory` |
| `>= 5` | `usable` |

A missing group or peer median is always `insufficient`.

`eligible = true` means the record is a non-flat behavioral pattern candidate with at least exploratory evidence. It does **not** mean statistically significant, causal, or recommendation-ready.

## Behavioral metrics only

Evidence candidates are emitted only for metrics marked `comparison_role = behavior`.

Lifetime Views and Reach are intentionally excluded because content age is not normalized yet. They remain descriptive in the aggregation layer until comparable checkpoint snapshots such as D1, D3, D7, D30, and D90 exist.

## Delta scales

Lumy-derived rates are ratios. For example:

- group Save Rate `0.15`;
- peer Save Rate `0.05`;
- delta `0.10` = **10 percentage points**;
- relative lift `2.0` = **200% above the peer median**.

Provider `skip_rate` already uses a percentage scale. For example:

- group Skip Rate `30`;
- peer Skip Rate `50`;
- delta `-20` = **20 percentage points lower**;
- relative lift `-0.4` = **40% lower relative to the peer median**.

If the peer median is zero, relative lift is `NULL` because percentage change from zero is undefined. The absolute delta remains valid.

## Explicit non-goals

This layer does not:

- generate prose or AI explanations;
- label a pattern as causally better or worse;
- run significance tests or confidence intervals;
- correct for content age, content type, or hidden confounders;
- combine several dimensions into a universal score;
- recommend a topic, Hook, CTA, or production plan.

Those responsibilities belong to later Pattern, Decision Support, Experimentation, and AI layers.
