# Decision Context Export

Lumy can export a deterministic decision context package for manual analysis outside the product, including use with an external AI assistant.

The export does not generate recommendations and does not ask an LLM to calculate source-of-truth metrics. It reuses Lumy's existing analytical services and preserves their semantics.

## Access

The global panel topbar exposes **Decision Context** with two downloads:

- JSON — structured machine-readable context.
- Markdown — copy/paste-friendly context for manual AI analysis.

Routes:

```text
/panel/exports/decision-context/json
/panel/exports/decision-context/markdown
```

## Package contents

The V1 package contains:

- account summary and latest account-insight period;
- analytics, annotation, and Primary Hook coverage;
- behavior baseline for High Intent, Save Rate, Share Rate, Retention, and Skip Rate;
- compact audience summary;
- top content by Views and High Intent, including Content DNA;
- eligible deterministic evidence for every Content Intelligence dimension;
- explicit data-quality and interpretation guardrails.

Each exported evidence item has a stable-in-context `evidence_id`:

```text
<dimension>:<group_key>:<metric>
```

For example:

```text
primary_hook_type:curiosity:high_intent_rate
```

When using the package with an AI assistant, recommendations should cite one or more exported `evidence_id` values and clearly distinguish `exploratory` evidence from `usable` evidence.

## Evidence policy

The export includes only evidence candidates already marked `eligible` by Lumy's deterministic evidence layer. This means:

- missing comparisons are excluded;
- `insufficient` comparisons are excluded;
- `same` comparisons are excluded;
- `exploratory` and `usable` comparisons may be included.

No statistical-significance or causal claim is added by the export.

## Metric semantics

- ratio metrics are stored as `0–1` in JSON;
- Skip Rate remains provider percent `0–100`;
- `NULL` remains missing/unknown and is never converted to zero;
- each content uses its single latest factual metric snapshot;
- Views and Reach remain lifetime scale metrics and may be age-biased until checkpoint cohorts are available.

Markdown renders ratios as percentages and ratio deltas as percentage points for readability.

## Architecture

```text
DashboardAnalytics
        +
ContentIntelligenceAnalytics
        +
ContentIntelligenceEvidence
        ↓
DecisionContextExport
        ↓
JSON / Markdown
        ↓
Manual analysis / external AI
```

The export is intentionally a transport layer over Lumy's deterministic facts. It is not an AI recommendation engine.
