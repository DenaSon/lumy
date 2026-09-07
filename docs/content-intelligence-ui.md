# Content Intelligence UI

The Content Intelligence analysis workspace exposes Lumy's deterministic aggregation, peer-baseline comparison, and structured evidence layers in the panel.

## Routes

- `/panel/intelligence/analysis` is the read-only analysis workspace and the primary sidebar destination for Content Intelligence.
- `/panel/intelligence` remains the taxonomy and annotation-coverage setup workspace.

## Dimension navigation

The analysis workspace supports the same primary-attribution dimensions as `ContentIntelligenceAnalytics`:

- Primary Hook Type
- Primary Hook Source
- Primary Topic
- Primary Pillar
- Goal
- CTA Type
- Production Style

The selected dimension is stored in the `dimension` query parameter. Unsupported values fall back to `primary_hook_type`.

## Overall baseline

The first analytical section shows the inclusive overall baseline for the selected dimension. It reports medians and metric-specific sample sizes for the V1 primary behavior metrics:

- High Intent Rate
- Save Rate
- Share Rate
- Retention Rate
- Skip Rate

Missing values remain missing and are not rendered as zero.

## Group vs peer table

Each attributed group is compared with a peer baseline that excludes that group. The table shows:

- group sample size and V1 sample status;
- group median;
- peer median;
- absolute delta;
- group and peer metric sample sizes.

The UI does not label a numerical direction as good or bad. For example, a lower Skip Rate and a lower Save Rate both have the raw direction `below`; interpretation belongs to a later decision layer.

## Evidence candidates

The evidence section uses `ContentIntelligenceEvidence` directly. It surfaces structured behavior-metric candidates with:

- group, peer, and overall medians;
- independent sample sizes;
- absolute delta;
- relative lift when the peer median is non-zero;
- evidence status;
- raw numerical direction.

Views and Reach are intentionally excluded because the current snapshots are lifetime scale metrics and are not age-normalized.

`eligible` means the comparison has at least exploratory sample support and a non-flat numerical difference. It does **not** mean statistical significance, causality, or a recommendation.

## Guardrails

The UI is a read-only projection. It does not persist aggregate values, evidence candidates, scores, claims, or recommendations. `usable` still means only that the V1 minimum sample threshold was met. Confounder control, significance testing, age-normalized cohorts, experimentation, and AI decision support remain later layers.
