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

### `topics`

Topics belong to one content pillar. Slugs are unique within a pillar.

Topics are reusable across many content items.

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

## Design invariants

1. Manual intelligence never overwrites factual provider data.
2. Hooks are first-class records, not a single column on `contents`.
3. A content item can have multiple topics.
4. One annotation record represents the current structured manual classification of a content item.
5. Taxonomy values are flexible strings in V1; database enums are deliberately avoided.
6. Deleting content cascades its hooks, annotation, and topic assignments.
7. Deleting taxonomy is discouraged; `is_active` is the normal lifecycle control for pillars and topics.
8. Derived KPIs and performance scores are not stored in these tables.

## Analysis enabled by this model

Examples:

- Hook Type → Skip Rate
- Hook Source → Retention
- Hook → Save Rate
- Hook → Share Rate
- Topic → Reach
- Topic → High Intent Rate
- Pillar → Retention
- Content Goal → Performance profile
- Production Style → Watch Time

This layer prepares Lumy for the next phase: provider ingestion followed by statistical Content Intelligence.
