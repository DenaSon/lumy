# Instagram Sync Operations

Lumy keeps the existing full Instagram sync command and also supports running one operational stage at a time.

## Full sync

```bash
php artisan lumy:sync-instagram
```

Optional content-analytics date bounds remain available:

```bash
php artisan lumy:sync-instagram --from=2026-06-10 --to=2026-09-07
```

The full sync keeps the existing orchestration order:

1. Contents
2. Content analytics
3. Account insights
4. Demographics
5. Follower history

## Staged sync

Use `--only` when only one provider workflow should run:

```bash
php artisan lumy:sync-instagram --only=contents
php artisan lumy:sync-instagram --only=content-analytics --from=2026-09-01 --to=2026-09-07
php artisan lumy:sync-instagram --only=account-insights --from=2026-08-10 --to=2026-09-07
php artisan lumy:sync-instagram --only=demographics
php artisan lumy:sync-instagram --only=followers --from=2026-09-01 --to=2026-09-07
```

Canonical stage names are:

- `contents`
- `content-analytics`
- `account-insights`
- `demographics`
- `followers`

An invalid stage exits non-zero and prints the allowed values.

## Local account requirement

A staged sync operates against the local `social_accounts` row matching `ZERNIO_ACCOUNT_ID` and provider `zernio`.

On a completely new database, bootstrap that row with one full sync first:

```bash
php artisan lumy:sync-instagram
```

After that, individual stages can be run independently without triggering unrelated provider requests.

## Date semantics

`contents` and `demographics` are not date-scoped. Passing `--from` or `--to` to either stage is rejected rather than silently ignored.

`content-analytics` accepts a maximum range of 365 days.

`account-insights` accepts a maximum range of 89 days, matching the provider range constraint already observed by Lumy.

`followers` defaults to the previous 89 days when no explicit range is supplied, but an explicit older start date is not artificially capped by Lumy.

All command dates are interpreted as UTC calendar dates and must satisfy `from <= to`.

## Exit codes and auditability

The command exits with status `0` only when the requested workflow completes without row-level failures.

It exits non-zero when:

- the stage is invalid;
- the local account is missing;
- the requested dates are invalid for that stage;
- the provider/service throws an exception;
- any returned stage result has `failed_count > 0`.

Row-level errors are printed as warnings, while the authoritative operational history remains in `sync_runs`.

A successful staged run updates `social_accounts.last_synced_at`; it does not alter unrelated sync stages.

## Why staged sync exists

The staged command is the operational boundary needed for later scheduling and incremental ingestion. It lets Lumy run expensive or rate-limited workflows independently, which is especially important for content analytics, account insights, demographics, and follower history.
