# Lumy Core Data Model v1

This document defines the first persistence layer for Lumy's observable Instagram data.

## Scope

PR #2 covers only factual data and synchronization state:

- social accounts
- contents
- content media
- content metric snapshots
- account metric snapshots
- demographic snapshots
- sync runs

Manual Content Intelligence data such as Hooks, Pillars, Topics, CTAs, Goals, and annotations belongs to the next phase.

## Core Invariants

### Database is the source of truth

Provider APIs are ingestion sources. Lumy's normalized local database is the application source of truth.

### Historical analytics are append-only

Content and account analytics are stored as snapshots. New syncs must append a new snapshot instead of overwriting historical metrics.

### Missing is not zero

Unavailable provider metrics are stored as `NULL`, not `0`.

Examples:

- `saves = NULL` means the provider did not supply a usable value.
- `saves = 0` means the provider supplied an actual zero.

### Content identity

A platform post is unique inside one social account by:

`(social_account_id, platform_post_id)`

The same platform post ID may exist under another social account.

### Raw provider payloads

Important provider responses may be retained in nullable JSON columns for audit/debugging while normalized columns remain the primary application interface.

## Tables

### `social_accounts`

Represents an Instagram account and its provider mapping.

Important fields:

- platform / provider
- username / display name / account type
- platform and provider IDs
- connection and token timestamps
- sync status

API credentials are not stored in this table.

### `contents`

Represents one published platform content item such as a Reel, Feed post, image, video, or Carousel.

Important fields:

- platform and provider post IDs
- permalink
- content/media product type
- caption
- publish timestamp
- analytics status

Initial analytics statuses:

- `pending`
- `available`
- `unavailable`
- `failed`

### `content_media`

Stores media items belonging to a content item, including ordered Carousel slides and Reel/video metadata.

### `content_metric_snapshots`

Append-only content performance observations.

Metrics include:

- impressions / reach / views
- likes / comments / shares / saves / reposts / follows
- average and total watch time
- skip rate
- video duration
- provider engagement rate

Snapshot types may include:

- `initial`
- `scheduled`
- `manual`
- `backfill`
- `delta`
- `webhook`

Snapshot windows may include:

- `24h`
- `72h`
- `7d`
- `30d`
- `90d`
- `lifetime`

### `account_metric_snapshots`

Stores account-level observations for a provider period/window.

`follows_and_unfollows` is signed because a net follower delta can be negative.

### `demographic_snapshots`

Stores relational demographic rows for:

- age
- gender
- country
- city

`is_partial` marks datasets that are provider-limited, for example top-N cities.

Unknown demographic dimensions must be preserved when supplied.

### `sync_runs`

Audit trail for provider synchronization.

Tracks:

- provider
- sync type
- status
- start/end timestamps
- cursor
- discovered/created/updated/failed counts
- errors
- request metadata

Initial sync types:

- `contents`
- `content_analytics`
- `account_analytics`
- `demographics`
- `followers`
- `delta`

Initial statuses:

- `running`
- `completed`
- `partial`
- `failed`

## Derived Metrics

Derived KPIs are not persisted in this phase.

They will be calculated in the Analytics layer from snapshot data, including:

- save rate
- share rate
- comment rate
- like rate
- high-intent rate
- retention
- age-normalized performance
- growth velocity / decay

If a required denominator is missing or zero, the derived result must be `NULL` rather than an invented value.

## Next Phase

PR #3 will add the Content Intelligence model:

- content hooks
- content pillars
- topics
- content-topic relationships
- content annotations
