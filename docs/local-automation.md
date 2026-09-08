# Local automation

Lumy runs locally. The Laravel scheduler therefore runs only while the local development process is alive.

## Start the whole local system

```bash
composer dev
```

The development script runs these long-lived processes together:

- Laravel HTTP server
- queue listener
- application log tail
- Vite development server
- Laravel scheduler worker (`php artisan schedule:work`)

Stopping `composer dev` stops scheduled synchronization as well.

Because a local machine may be off at any particular clock time, Lumy does not rely on fixed overnight sync times. The scheduler runs a lightweight freshness check every minute. On startup, the first scheduler minute evaluates the last successful syncs and refreshes only stages that are stale.

## Freshness-aware sync

The local freshness command is:

```bash
php artisan lumy:sync-instagram-fresh
```

It reads completed `sync_runs` for the configured Instagram account and applies these V1 freshness thresholds:

- content metadata + content analytics: stale after 2 hours
- account insights: stale after 24 hours
- demographics: stale after 24 hours
- follower history: stale after 24 hours

Content freshness requires both `contents` and `content_analytics` to have a recent completed run. Failed or partial runs do not make a stage fresh, so a later check can retry them.

The scheduler uses:

```bash
php artisan lumy:sync-instagram-fresh --soft
```

`--soft` keeps a temporary provider/config/bootstrap failure from terminating the local scheduler process. The underlying sync command still writes failure details to `sync_runs` and logs. Running the command manually without `--soft` returns a non-zero exit code when a due refresh fails.

A blank database still needs one full sync first so the configured local Zernio social account exists:

```bash
php artisan lumy:sync-instagram
```

## Incremental Instagram sync

The frequent content path is intentionally smaller than a full historical sync:

```bash
php artisan lumy:sync-instagram-incremental
```

It refreshes external content metadata and content analytics for a recent rolling window. By default the analytics window is the last 30 days; it can be changed explicitly:

```bash
php artisan lumy:sync-instagram-incremental --days=14
```

The command accepts 1–90 days.

## Manual control

The staged commands remain available for troubleshooting or explicit refreshes:

```bash
php artisan lumy:sync-instagram --only=contents
php artisan lumy:sync-instagram --only=content-analytics --from=2026-09-01 --to=2026-09-07
php artisan lumy:sync-instagram --only=account-insights --from=2026-08-10 --to=2026-09-07
php artisan lumy:sync-instagram --only=demographics
php artisan lumy:sync-instagram --only=followers --from=2026-09-01 --to=2026-09-07
```

All stage executions continue to write `sync_runs`, which remains the operational audit source for freshness, failures, counts, and provider request history.
