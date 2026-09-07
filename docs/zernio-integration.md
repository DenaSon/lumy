# Lumy — Zernio Integration

Lumy uses Zernio as the provider bridge for Instagram metadata and analytics. The local database remains the application source of truth.

## Configuration

Add the following values to the local `.env` file:

```env
ZERNIO_API_KEY=
ZERNIO_ACCOUNT_ID=
ZERNIO_PROFILE_ID=
ZERNIO_USERNAME=
```

`ZERNIO_API_KEY` must never be committed to Git.

Default API base URL:

```text
https://zernio.com/api/v1
```

## Initial sync command

```bash
php artisan lumy:sync-instagram
```

Optional post-analytics range:

```bash
php artisan lumy:sync-instagram --from=2026-06-10 --to=2026-09-07
```

Post analytics are capped to a maximum 366-day request range by the integration. Account insights and Instagram follower history use the trailing 89 days because those endpoints have shorter provider limits.

## Sync flow

```text
Account Health
    ↓
External Post Refresh
    ↓
External Post Metadata
    ↓
Post Analytics
    ↓
Account Insights
    ↓
Audience Demographics
    ↓
Follower History
```

Each stage writes a `sync_runs` audit record.

## Endpoints used

- `GET /accounts/{accountId}/health`
- `POST /posts/sync-external`
- `GET /posts?source=external&accountId=...`
- `GET /analytics`
- `GET /analytics/instagram/account-insights`
- `GET /analytics/instagram/demographics`
- `GET /analytics/instagram/follower-history`

The client also exposes `GET /analytics/delta` for the later incremental-sync phase.

## Data rules

### Content identity

A platform post is matched by:

```text
social_account_id + platform_post_id
```

The Zernio external-post ID is stored separately in `provider_post_id` so analytics responses can be resolved by either provider or platform identity.

### Analytics availability

Analytics are only stored as a real `content_metric_snapshots` row when Zernio returns an explicit `lastUpdated` timestamp.

Therefore:

```text
analytics payload with lastUpdated = null
```

is treated as pending/unavailable data rather than a real zero-performance snapshot.

Zero values are retained when `lastUpdated` is present.

### Snapshots

Post analytics are cumulative provider values and are stored as append-only `lifetime` snapshots. A snapshot with an unchanged provider `lastUpdated` timestamp is not duplicated.

Account insights are appended as range-based account snapshots.

Follower history is imported as daily account snapshots and includes:

- follower count
- followers gained
- followers lost

### Demographics

Age, gender, city, and country values are persisted relationally. City and country snapshots are flagged as partial because Zernio returns only top entries for demographic dimensions.

### Raw payloads

Normalized application fields are stored alongside provider payload JSON for audit and debugging.

## Scheduling

V1 intentionally does not enable an automatic scheduler. Lumy is a local application, so the sync command is run manually until the desired local scheduling strategy is decided.

The inherited xDeploy/TIP scheduled commands have been removed.

## Future incremental sync

The next ingestion optimization can use:

```text
analytics.synced webhook
        ↓
GET /analytics/delta
        ↓
last stored cursor
```

For a local-only installation, periodic delta polling may be preferable to exposing a webhook endpoint.
