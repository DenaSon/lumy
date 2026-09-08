# Lumy — Content Intelligence Model

This document defines the V1 manual intelligence layer that sits on top of Lumy's factual content and analytics data.

## Purpose

The factual layer answers **what happened**. The Content Intelligence layer captures structured information required to analyze **what kind of content it was** and later explain **why it performed that way**.

The model intentionally keeps manual classification separate from `contents` and analytics snapshots.

## Entities

### `content_hooks`

Stores one or more manually identified hooks for a content item.

Fields include:

- hook text
- hook type
- hook source
- ordering position
- primary-hook flag
- notes

A content item may have multiple hooks, for example cover text, video overlay, spoken opening, Carousel first slide, and caption hook.

Initial hook types are application-level taxonomy values such as:

- `question`
- `curiosity`
- `problem`
- `how_to`
- `warning`
- `contrarian`
- `list`
- `result`
- `news`
- `story`
- `statement`

Initial sources include:

- `cover`
- `video_overlay`
- `spoken`
- `carousel_first_slide`
- `caption`
- `other`

These are not database enums so the taxonomy can evolve without schema migrations.

### `content_pillars`

Top-level content taxonomy. Pillars are reusable and can be deactivated instead of deleted.

Examples may eventually include Linux, Server, DevOps, Security, Networking, or Programming, but the official Lumixo taxonomy should be based on real content history rather than hard-coded assumptions.

Pillars have an explicit `sort_order` used only for taxonomy presentation and workflow ordering. It is not a performance rank.

### `topics`

Topics belong to one content pillar. Slugs are unique within a pillar.

Topics are reusable across many content items. Topics also have an explicit `sort_order` for taxonomy presentation. This ordering is manual taxonomy structure, not an analytics rank.

### `content_topic`

Many-to-many pivot between content and topics.

A content item can have multiple topics and each assignment can indicate whether it is the primary topic.

### `content_annotations`

One-to-one manual annotation record for each content item.

It stores non-hook intelligence such as:

- primary pillar
- content goal
- CTA type
- target audience
- production style
- cover style
- notes
- annotation timestamp

Initial content goals may include `awareness`, `education`, `community`, `growth`, `conversion`, `experiment`, and `other`.

Initial CTA values may include `none`, `comment`, `save`, `share`, `follow`, `link`, `dm`, and `other`.

These values also remain flexible strings in V1.

## Relationships

```text
contents
  ├── has many content_hooks
  ├── has one content_annotation
  └── belongs to many topics
                       │
                       └── belongs to content_pillars

content_annotations
  └── optionally belongs to primary content_pillar
```

## Taxonomy management

Lumy manages Pillars and Topics from the Content Intelligence workspace.

V1 lifecycle rules:

1. Pillars and Topics can be created and edited.
2. Normal retirement is deactivation, not deletion.
3. Deactivation never removes existing content assignments.
4. Previously assigned inactive taxonomy remains visible in annotation workflows so historical classifications stay interpretable.
5. Pillar and Topic sort order is manual information architecture only; it must never be interpreted as content performance rank.
6. Lumy does not seed an assumed official Lumixo taxonomy. The taxonomy is built from actual editorial history and strategy.

## Annotation coverage

Content Intelligence analysis must expose dataset coverage before presenting patterns.

The V1 coverage projection counts content with:

- at least one factual metric snapshot
- an annotation record
- a primary pillar
- a primary topic
- a primary hook
- any hook type
- any hook source
- a content goal

Coverage is reported as observed counts and selected rates. Missing annotation is never treated as a negative category, and an empty dataset produces a missing rate rather than an invented `0%` completion rate.

Coverage is a read-only projection and is not persisted.

## V1 analysis dimensions

Primary-attribution analysis currently supports:

- Primary Hook Type
- Primary Hook Source
- Primary Topic
- Primary Pillar
- Format
- Goal
- CTA Type
- Production Style

Hook and Topic analyses use only their primary assignment so one content item cannot inflate a primary comparison by appearing multiple times in the same dimension.

`Format` is factual rather than manual. It comes from normalized `contents.content_type`, for example `reel`, `carousel`, or `feed`. Missing content type is excluded instead of being converted into an invented `Unknown` group.

Every group uses the same median, metric-specific sample-size, peer-baseline, and evidence semantics. Views and Reach remain descriptive lifetime scale metrics until age-normalized checkpoints are available; behavioral metrics such as Save Rate, Share Rate, High Intent, Retention, and Skip Rate may surface as evidence candidates when the sample threshold is met.

## Design invariants

1. Manual intelligence never overwrites factual provider data.
2. Hooks are first-class records, not a single column on `contents`.
3. A content item can have multiple topics.
4. One annotation record represents the current structured manual classification of a content item.
5. Taxonomy values are flexible strings in V1; database enums are deliberately avoided.
6. Deleting content cascades its hooks, annotation, and topic assignments.
7. Deleting taxonomy is discouraged; `is_active` is the normal lifecycle control for pillars and topics.
8. Derived KPIs and performance scores are not stored in these tables.
9. Taxonomy ordering is editorial structure, not analytics ranking.
10. Coverage metrics must reflect observed fields only and never infer missing values.
11. Primary-attribution analysis counts one content item at most once per dimension group.
12. Factual Format analysis uses normalized content type and remains separate from manual annotation.

## Analysis enabled by this model

Examples:

- Hook Type → Skip Rate
- Hook Source → Retention
- Hook → Save Rate
- Hook → Share Rate
- Topic → Reach
- Topic → High Intent Rate
- Pillar → Retention
- Format → Save / Share / High Intent / Retention profile
- Content Goal → Performance profile
- Production Style → Watch Time

This layer prepares Lumy for statistical Content Intelligence: attribution rules, group-level medians, sample-size semantics, baseline comparison, and evidence-backed patterns.
