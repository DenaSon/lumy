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

## Incremental Instagram sync

The frequent path is intentionally smaller than a full historical sync:

```bash
php artisan lumy:sync-instagram-incremental
```

It refreshes external content metadata and content analytics for a recent rolling window. By default the analytics window is the last 30 days; it can be changed explicitly:

```bash
php artisan lumy:sync-instagram-incremental --days=14
```

The command accepts 1–90 days. It requires the configured local Zernio social account to already exist. A blank database should run one full sync first.

## Local schedule

While `composer dev` is running:

- incremental content sync: every 2 hours
- account insights: daily at 02:00 application local time
- demographics: daily at 02:15
- follower history: daily at 02:30

The daily stages compute their rolling date windows when the command executes, so dates do not become stale in a long-running local scheduler process.

All underlying stage executions continue to write `sync_runs`. Command failures return non-zero exit codes and remain visible in the scheduler/log output.

## Manual control

The staged commands remain available for troubleshooting or explicit refreshes:

```bash
php artisan lumy:sync-instagram --only=contents
php artisan lumy:sync-instagram --only=content-analytics --from=2026-09-01 --to=2026-09-07
php artisan lumy:sync-instagram --only=account-insights --from=2026-08-10 --to=2026-09-07
php artisan lumy:sync-instagram --only=demographics
php artisan lumy:sync-instagram --only=followers --from=2026-09-01 --to=2026-09-07
```
