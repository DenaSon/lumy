<?php

use App\Integrations\Zernio\InstagramSyncService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('lumy:sync-instagram {--from=} {--to=}', function () {
    try {
        $summary = app(InstagramSyncService::class)->sync(
            $this->option('from'),
            $this->option('to'),
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return 1;
    }

    $rows = [];

    foreach (['contents', 'content_analytics', 'account_analytics', 'demographics', 'followers'] as $key) {
        $result = $summary[$key] ?? [];
        $rows[] = [
            $key,
            $result['discovered_count'] ?? 0,
            $result['created_count'] ?? 0,
            $result['updated_count'] ?? 0,
            $result['failed_count'] ?? 0,
        ];
    }

    $this->info('Instagram sync completed.');
    $this->table(
        ['Sync', 'Discovered', 'Created', 'Updated', 'Failed'],
        $rows,
    );

    return 0;
})->purpose('Synchronize Lumy with the configured Instagram account through Zernio.');
