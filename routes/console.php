<?php

use App\Integrations\Zernio\InstagramSyncService;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('lumy:sync-instagram {--only= : Run one stage: contents, content-analytics, account-insights, demographics, followers} {--from= : UTC start date (YYYY-MM-DD) for date-aware stages} {--to= : UTC end date (YYYY-MM-DD) for date-aware stages}', function () {
    $stageMap = [
        'contents' => 'contents',
        'content-analytics' => 'content_analytics',
        'account-insights' => 'account_analytics',
        'demographics' => 'demographics',
        'followers' => 'followers',
    ];
    $only = trim((string) $this->option('only'));
    $fromOption = $this->option('from');
    $toOption = $this->option('to');

    if ($only !== '' && ! array_key_exists($only, $stageMap)) {
        $this->error('Invalid --only stage: '.$only);
        $this->line('Allowed stages: '.implode(', ', array_keys($stageMap)));

        return 1;
    }

    $resolveRange = function (int $defaultDays, ?int $maxDays = null) use ($fromOption, $toOption): array {
        $to = $toOption
            ? CarbonImmutable::parse((string) $toOption, 'UTC')->startOfDay()
            : CarbonImmutable::now('UTC')->startOfDay();
        $from = $fromOption
            ? CarbonImmutable::parse((string) $fromOption, 'UTC')->startOfDay()
            : $to->subDays($defaultDays);

        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('The sync start date must be before or equal to the end date.');
        }

        if ($maxDays !== null && $from->diffInDays($to) > $maxDays) {
            throw new InvalidArgumentException("The selected stage supports a maximum date range of {$maxDays} days.");
        }

        return [$from->toDateString(), $to->toDateString()];
    };

    try {
        $service = app(InstagramSyncService::class);

        if ($only === '') {
            $summary = $service->sync($fromOption, $toOption);
            $syncKeys = array_values($stageMap);
        } else {
            if (in_array($only, ['contents', 'demographics'], true) && ($fromOption || $toOption)) {
                throw new InvalidArgumentException("--from/--to are not applicable to the {$only} stage.");
            }

            $providerAccountId = trim((string) config('zernio.account_id'));

            if ($providerAccountId === '') {
                throw new RuntimeException('ZERNIO_ACCOUNT_ID is not configured.');
            }

            $account = SocialAccount::query()
                ->where('provider', 'zernio')
                ->where('provider_account_id', $providerAccountId)
                ->first();

            if ($account === null) {
                throw new RuntimeException('No local Zernio social account exists yet. Run `php artisan lumy:sync-instagram` once to bootstrap the account before using --only.');
            }

            $result = match ($only) {
                'contents' => $service->syncContents($account),
                'content-analytics' => (function () use ($service, $account, $resolveRange) {
                    [$from, $to] = $resolveRange(365, 365);

                    return $service->syncContentAnalytics($account, $from, $to);
                })(),
                'account-insights' => (function () use ($service, $account, $resolveRange) {
                    [$from, $to] = $resolveRange(89, 89);

                    return $service->syncAccountInsights($account, $from, $to);
                })(),
                'demographics' => $service->syncDemographics($account),
                'followers' => (function () use ($service, $account, $resolveRange) {
                    [$from, $to] = $resolveRange(89);

                    return $service->syncFollowerHistory($account, $from, $to);
                })(),
            };

            $account->update(['last_synced_at' => now()]);
            $summary = [$stageMap[$only] => $result];
            $syncKeys = [$stageMap[$only]];
        }
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $rows = [];
    $hasFailures = false;

    foreach ($syncKeys as $key) {
        $result = $summary[$key] ?? [];
        $failed = (int) ($result['failed_count'] ?? 0);
        $hasFailures = $hasFailures || $failed > 0;
        $rows[] = [
            $key,
            $result['discovered_count'] ?? 0,
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $failed,
        ];

        foreach (array_slice($result['errors'] ?? [], 0, 10) as $error) {
            $this->warn("{$key}: {$error}");
        }
    }

    $this->info($only === '' ? 'Instagram sync completed.' : "Instagram {$only} sync completed.");
    $this->table(
        ['Sync', 'Discovered', 'Created', 'Updated', 'Failed'],
        $rows,
    );

    if ($hasFailures) {
        $this->error('One or more sync rows failed. See sync_runs and the warnings above for details.');

        return 1;
    }

    return 0;
})->purpose('Synchronize all or one Lumy Instagram data stage through Zernio.');

Artisan::command('lumy:sync-instagram-incremental {--days=30 : Rolling number of UTC days to refresh for content analytics (1-90)}', function () {
    $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

    if ($days === false || $days < 1 || $days > 90) {
        $this->error('--days must be an integer between 1 and 90.');

        return 1;
    }

    $providerAccountId = trim((string) config('zernio.account_id'));

    if ($providerAccountId === '') {
        $this->error('ZERNIO_ACCOUNT_ID is not configured.');

        return 1;
    }

    $account = SocialAccount::query()
        ->where('provider', 'zernio')
        ->where('provider_account_id', $providerAccountId)
        ->first();

    if ($account === null) {
        $this->error('No local Zernio social account exists yet. Run `php artisan lumy:sync-instagram` once to bootstrap the account.');

        return 1;
    }

    $to = CarbonImmutable::now('UTC')->startOfDay();
    $from = $to->subDays($days);

    try {
        $service = app(InstagramSyncService::class);
        $summary = [
            'contents' => $service->syncContents($account),
            'content_analytics' => $service->syncContentAnalytics($account, $from->toDateString(), $to->toDateString()),
        ];
        $account->update(['last_synced_at' => now()]);
    } catch (Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $rows = [];
    $hasFailures = false;

    foreach (['contents', 'content_analytics'] as $key) {
        $result = $summary[$key];
        $failed = (int) ($result['failed_count'] ?? 0);
        $hasFailures = $hasFailures || $failed > 0;
        $rows[] = [
            $key,
            $result['discovered_count'] ?? 0,
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $failed,
        ];

        foreach (array_slice($result['errors'] ?? [], 0, 10) as $error) {
            $this->warn("{$key}: {$error}");
        }
    }

    $this->info("Incremental Instagram sync completed for the last {$days} days.");
    $this->table(['Sync', 'Discovered', 'Created', 'Updated', 'Failed'], $rows);

    return $hasFailures ? 1 : 0;
})->purpose('Refresh recent Instagram content metadata and analytics without a full historical sync.');

Schedule::command('lumy:sync-instagram-incremental')
    ->name('lumy-instagram-incremental')
    ->everyTwoHours()
    ->withoutOverlapping();

Schedule::command('lumy:sync-instagram --only=account-insights')
    ->name('lumy-instagram-account-insights')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('lumy:sync-instagram --only=demographics')
    ->name('lumy-instagram-demographics')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('lumy:sync-instagram --only=followers')
    ->name('lumy-instagram-followers')
    ->dailyAt('02:30')
    ->withoutOverlapping();
