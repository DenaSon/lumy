# Lumy — Manual Content Annotation UI

The Manual Annotation UI is Lumy's V1 interface for turning factual content records into a structured Content Intelligence dataset.

## Route

`/panel/content/{content}/annotate`

The Content Catalog links every content item to this page and reports whether a current annotation record exists.

## Workflow

The page is designed for repeatable batch annotation:

1. inspect the content preview and current performance snapshot
2. classify Content DNA
3. assign topics and an optional primary topic
4. capture one or more hooks
5. choose one optional primary hook
6. Save or Save & Next

Previous/newer and next/older navigation follows publication chronology inside the same social account.

## Content DNA fields

The page edits the existing `content_annotations` record for the content:

- primary pillar
- goal
- CTA type
- target audience
- production style
- cover style
- notes
- `annotated_at`

Initial goal values follow the application taxonomy already defined by the Content Intelligence model:

- awareness
- education
- community
- growth
- conversion
- experiment
- other

Initial CTA values:

- none
- comment
- save
- share
- follow
- link
- dm
- other

These remain application-level string taxonomies rather than database enums.

## Topics

A content item can be assigned multiple topics through `content_topic`.

Exactly zero or one assigned topic may be marked `is_primary = true`.

Inactive taxonomy entries already assigned to the content remain visible while editing so historical classifications are not silently discarded. The UI does not invent or seed Lumixo pillars/topics; official taxonomy remains a separate product decision.

## Hooks

Hooks remain first-class `content_hooks` records. The UI supports multiple rows, ordering, removal, editing, and one optional primary hook.

Initial hook types:

- question
- curiosity
- problem
- how_to
- warning
- contrarian
- list
- result
- news
- story
- statement

Initial hook sources:

- cover
- video_overlay
- spoken
- carousel_first_slide
- caption
- other

Blank hook rows are ignored. Existing hook IDs are retained when edited, and hooks removed from the form are deleted for that content. At most one submitted hook is persisted as primary.

## Preview and performance context

The annotation screen intentionally shows provider facts and Lumy-derived KPIs next to the manual form, including:

- content preview / cover media
- caption and permalink
- publish timestamp
- views / reach / saves
- high-intent rate
- retention rate
- provider skip rate

These values are read-only and continue to come from the factual/analytics layers. Manual annotation never changes provider data.

## Persistence

`ContentAnnotationWriter` owns the transactional write operation for:

- the one-to-one annotation record
- topic pivot synchronization
- hook synchronization

A successful save updates `annotated_at` to the current time.

No migration is required for this UI because the Content Intelligence schema was already created in PR #3.
