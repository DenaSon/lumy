<?php

namespace App\Integrations\Zernio;

use App\Models\Content;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class InstagramSyncService
{
    public function __construct(private readonly ZernioClient $client) {}

    public function sync(?string $fromDate = null, ?string $toDate = null): array
    {
        $providerAccountId = $this->configuredAccountId();
        $health = $this->client->accountHealth($providerAccountId);
        $account = $this->upsertAccount($providerAccountId, $health);

        [$postFrom, $to] = $this->postAnalyticsRange($fromDate, $toDate);
        $accountSince = $to->subDays(89);

        $summary = [
            'social_account_id' => $account->id,
            'provider_account_id' => $providerAccountId,
            'contents' => $this->syncContents($account),
            'content_analytics' => $this->syncContentAnalytics($account, $postFrom->toDateString(), $to->toDateString()),
            'account_analytics' => $this->syncAccountInsights($account, $accountSince->toDateString(), $to->toDateString()),
            'demographics' => $this->syncDemographics($account),
            'followers' => $this->syncFollowerHistory($account, $accountSince->toDateString(), $to->toDateString()),
        ];

        $account->update([
            'last_synced_at' => now(),
            'provider_payload' => $health,
        ]);

        return $summary;
    }

    public function syncContents(SocialAccount $account): array
    {
        return $this->track($account, 'contents', [], function () use ($account) {
            $refreshPayload = $this->client->syncExternalPosts($account->provider_account_id);
            $page = 1;
            $pages = 1;
            $result = $this->emptyResult();

            do {
                $payload = $this->client->listExternalPosts($account->provider_account_id, $page, 100);
                $posts = $this->rows($payload);
                $pages = max(1, (int) data_get($payload, 'pagination.pages', 1));
                $result['discovered_count'] += count($posts);

                foreach ($posts as $post) {
                    try {
                        $action = $this->persistExternalPost($account, $post);
                        $result[$action.'_count']++;
                    } catch (Throwable $exception) {
                        $result['failed_count']++;
                        $result['errors'][] = $exception->getMessage();
                    }
                }

                $page++;
            } while ($page <= $pages);

            $result['provider_payload'] = [
                'refresh' => $refreshPayload,
                'pages' => $pages,
            ];

            return $result;
        });
    }

    public function syncContentAnalytics(SocialAccount $account, string $fromDate, string $toDate): array
    {
        return $this->track($account, 'content_analytics', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ], function () use ($account, $fromDate, $toDate) {
            $page = 1;
            $pages = 1;
            $result = $this->emptyResult();

            do {
                $payload = $this->client->analytics([
                    'platform' => 'instagram',
                    'accountId' => $account->provider_account_id,
                    'source' => 'external',
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'limit' => 100,
                    'page' => $page,
                    'sortBy' => 'date',
                    'order' => 'desc',
                ]);

                $rows = $this->rows($payload);
                $pages = max(1, (int) data_get($payload, 'pagination.pages', 1));
                $result['discovered_count'] += count($rows);

                foreach ($rows as $row) {
                    try {
                        $created = $this->persistAnalyticsRow($account, $row);
                        $result[$created ? 'created_count' : 'updated_count']++;
                    } catch (Throwable $exception) {
                        $result['failed_count']++;
                        $result['errors'][] = $exception->getMessage();
                    }
                }

                $page++;
            } while ($page <= $pages);

            $result['provider_payload'] = ['pages' => $pages];

            return $result;
        });
    }

    public function syncAccountInsights(SocialAccount $account, string $since, string $until): array
    {
        return $this->track($account, 'account_analytics', [
            'since' => $since,
            'until' => $until,
        ], function () use ($account, $since, $until) {
            $payload = $this->client->instagramAccountInsights(
                $account->provider_account_id,
                $since,
                $until,
                [
                    'reach',
                    'views',
                    'accounts_engaged',
                    'total_interactions',
                    'comments',
                    'likes',
                    'saves',
                    'shares',
                    'reposts',
                    'follows_and_unfollows',
                    'profile_links_taps',
                ],
            );

            $account->accountMetricSnapshots()->create([
                'period_start' => CarbonImmutable::parse($since, 'UTC')->startOfDay(),
                'period_end' => CarbonImmutable::parse($until, 'UTC')->endOfDay(),
                'captured_at' => now(),
                'reach' => $this->metricTotal($payload, 'reach'),
                'views' => $this->metricTotal($payload, 'views'),
                'accounts_engaged' => $this->metricTotal($payload, 'accounts_engaged'),
                'total_interactions' => $this->metricTotal($payload, 'total_interactions'),
                'likes' => $this->metricTotal($payload, 'likes'),
                'comments' => $this->metricTotal($payload, 'comments'),
                'saves' => $this->metricTotal($payload, 'saves'),
                'shares' => $this->metricTotal($payload, 'shares'),
                'reposts' => $this->metricTotal($payload, 'reposts'),
                'follows_and_unfollows' => $this->metricTotal($payload, 'follows_and_unfollows'),
                'profile_links_taps' => $this->metricTotal($payload, 'profile_links_taps'),
                'provider_payload' => $payload,
            ]);

            return [
                ...$this->emptyResult(),
                'discovered_count' => 1,
                'created_count' => 1,
                'provider_payload' => ['date_range' => data_get($payload, 'dateRange')],
            ];
        });
    }

    public function syncDemographics(SocialAccount $account): array
    {
        return $this->track($account, 'demographics', [], function () use ($account) {
            $payload = $this->client->instagramDemographics($account->provider_account_id);
            $capturedAt = now()->startOfSecond();
            $result = $this->emptyResult();

            foreach (['age', 'gender', 'city', 'country'] as $dimensionType) {
                $items = data_get($payload, "demographics.{$dimensionType}", []);

                if (! is_array($items)) {
                    continue;
                }

                foreach (array_values($items) as $index => $item) {
                    if (! is_array($item) || ! array_key_exists('dimension', $item) || ! array_key_exists('value', $item)) {
                        continue;
                    }

                    $account->demographicSnapshots()->create([
                        'captured_at' => $capturedAt,
                        'dimension_type' => $dimensionType,
                        'dimension' => (string) $item['dimension'],
                        'value' => (int) $item['value'],
                        'rank' => $index + 1,
                        'is_partial' => in_array($dimensionType, ['city', 'country'], true),
                        'provider_payload' => $item,
                    ]);

                    $result['discovered_count']++;
                    $result['created_count']++;
                }
            }

            $result['provider_payload'] = [
                'metric' => data_get($payload, 'metric'),
                'timeframe' => data_get($payload, 'timeframe'),
            ];

            return $result;
        });
    }

    public function syncFollowerHistory(SocialAccount $account, string $since, string $until): array
    {
        return $this->track($account, 'followers', [
            'since' => $since,
            'until' => $until,
        ], function () use ($account, $since, $until) {
            $payload = $this->client->instagramFollowerHistory($account->provider_account_id, $since, $until);
            $series = [];
            $metricColumns = [
                'follower_count' => 'followers_count',
                'followers_gained' => 'followers_gained',
                'followers_lost' => 'followers_lost',
            ];

            foreach ($metricColumns as $metric => $column) {
                $values = data_get($payload, "metrics.{$metric}.values", []);

                if (! is_array($values)) {
                    continue;
                }

                foreach ($values as $point) {
                    if (! is_array($point) || empty($point['date']) || ! array_key_exists('value', $point)) {
                        continue;
                    }

                    $series[$point['date']][$column] = (int) $point['value'];
                }
            }

            ksort($series);
            $result = $this->emptyResult();
            $result['discovered_count'] = count($series);

            foreach ($series as $date => $values) {
                $capturedAt = CarbonImmutable::parse($date, 'UTC')->startOfDay();
                $snapshot = $account->accountMetricSnapshots()
                    ->where('captured_at', $capturedAt)
                    ->first();

                $created = $snapshot === null;
                $snapshot ??= $account->accountMetricSnapshots()->make();
                $snapshot->fill([
                    'period_start' => $capturedAt,
                    'period_end' => $capturedAt->endOfDay(),
                    'captured_at' => $capturedAt,
                    'followers_count' => $values['followers_count'] ?? null,
                    'followers_gained' => $values['followers_gained'] ?? null,
                    'followers_lost' => $values['followers_lost'] ?? null,
                    'provider_payload' => [
                        'source' => 'instagram_follower_history',
                        'date' => $date,
                        'values' => $values,
                    ],
                ]);
                $snapshot->save();

                $result[$created ? 'created_count' : 'updated_count']++;
            }

            $result['provider_payload'] = [
                'date_range' => data_get($payload, 'dateRange'),
            ];

            return $result;
        });
    }

    private function persistExternalPost(SocialAccount $account, array $post): string
    {
        $platformPostId = $this->platformPostId($post);

        if ($platformPostId === null) {
            throw new UnexpectedValueException('External post is missing platformPostId.');
        }

        return DB::transaction(function () use ($account, $post, $platformPostId) {
            $content = Content::firstOrNew([
                'social_account_id' => $account->id,
                'platform_post_id' => $platformPostId,
            ]);
            $created = ! $content->exists;
            $incomingStatus = $this->mapAnalyticsStatus(data_get($post, 'syncStatus'));

            $content->fill([
                'provider_post_id' => $post['_id'] ?? $post['postId'] ?? $post['id'] ?? null,
                'permalink' => $post['platformPostUrl'] ?? $post['permalink'] ?? $post['url'] ?? null,
                'media_product_type' => $post['mediaProductType'] ?? null,
                'content_type' => $this->contentType($post),
                'caption' => $post['content'] ?? $post['caption'] ?? null,
                'published_at' => $post['publishedAt'] ?? $post['timestamp'] ?? $post['createdAt'] ?? null,
                'analytics_status' => $incomingStatus ?? ($content->analytics_status ?: 'pending'),
                'platform_status' => $post['status'] ?? null,
                'provider_updated_at' => $post['updatedAt'] ?? null,
                'provider_payload' => $post,
            ]);
            $content->save();

            $this->replaceMedia($content, $post);

            return $created ? 'created' : 'updated';
        });
    }

    private function persistAnalyticsRow(SocialAccount $account, array $row): bool
    {
        $platformRow = $this->instagramPlatformRow($row);
        $platformPostId = $platformRow['platformPostId'] ?? $row['platformPostId'] ?? null;
        $providerPostId = $row['postId'] ?? null;

        $contentQuery = $account->contents();
        $content = $platformPostId
            ? (clone $contentQuery)->where('platform_post_id', $platformPostId)->first()
            : null;
        $content ??= $providerPostId
            ? (clone $contentQuery)->where('provider_post_id', $providerPostId)->first()
            : null;

        if ($content === null) {
            throw new UnexpectedValueException('Analytics row could not be matched to a local content record.');
        }

        $analytics = $platformRow['analytics'] ?? $row['analytics'] ?? null;
        $syncStatus = $platformRow['syncStatus'] ?? $row['syncStatus'] ?? null;
        $mappedStatus = $this->mapAnalyticsStatus($syncStatus);

        if ($mappedStatus !== null) {
            $content->update(['analytics_status' => $mappedStatus]);
        }

        if (! is_array($analytics) || empty($analytics['lastUpdated'])) {
            return false;
        }

        $providerUpdatedAt = CarbonImmutable::parse($analytics['lastUpdated']);

        if ($content->metricSnapshots()->where('provider_updated_at', $providerUpdatedAt)->exists()) {
            return false;
        }

        $snapshotType = $content->metricSnapshots()->exists() ? 'scheduled' : 'initial';

        $content->metricSnapshots()->create([
            'captured_at' => now(),
            'provider_updated_at' => $providerUpdatedAt,
            'snapshot_type' => $snapshotType,
            'snapshot_window' => 'lifetime',
            'impressions' => $this->nullableInteger($analytics, 'impressions'),
            'reach' => $this->nullableInteger($analytics, 'reach'),
            'views' => $this->nullableInteger($analytics, 'views'),
            'likes' => $this->nullableInteger($analytics, 'likes'),
            'comments' => $this->nullableInteger($analytics, 'comments'),
            'shares' => $this->nullableInteger($analytics, 'shares'),
            'saves' => $this->nullableInteger($analytics, 'saves'),
            'reposts' => $this->nullableInteger($analytics, 'reposts'),
            'follows' => $this->nullableInteger($analytics, 'follows'),
            'avg_watch_time_ms' => $this->nullableInteger($analytics, 'igReelsAvgWatchTime'),
            'total_watch_time_ms' => $this->nullableInteger($analytics, 'igReelsVideoViewTotalTime'),
            'skip_rate' => $this->nullableNumber($analytics, 'reelsSkipRate'),
            'video_duration_seconds' => $this->nullableNumber($analytics, 'videoDurationSeconds'),
            'provider_engagement_rate' => $this->nullableNumber($analytics, 'engagementRate'),
            'provider_payload' => $row,
        ]);

        $content->update(['analytics_status' => 'available']);

        return true;
    }

    private function replaceMedia(Content $content, array $post): void
    {
        $items = $post['mediaItems'] ?? $post['media'] ?? [];

        if (! is_array($items)) {
            $items = [];
        }

        $content->media()->delete();

        foreach (array_values($items) as $position => $item) {
            if (! is_array($item)) {
                continue;
            }

            $content->media()->create([
                'platform_media_id' => $item['platformMediaId'] ?? $item['id'] ?? null,
                'type' => (string) ($item['type'] ?? $post['mediaType'] ?? 'image'),
                'position' => $position,
                'remote_url' => $item['url'] ?? $item['mediaUrl'] ?? null,
                'thumbnail_url' => $item['thumbnail'] ?? $item['thumbnailUrl'] ?? null,
                'width' => isset($item['width']) ? (int) $item['width'] : null,
                'height' => isset($item['height']) ? (int) $item['height'] : null,
                'duration_seconds' => $item['durationSeconds'] ?? null,
                'provider_payload' => $item,
            ]);
        }
    }

    private function upsertAccount(string $providerAccountId, array $health): SocialAccount
    {
        $account = SocialAccount::firstOrNew([
            'provider' => 'zernio',
            'provider_account_id' => $providerAccountId,
        ]);

        $username = trim((string) ($health['username'] ?? config('zernio.username') ?? 'instagram'));

        $account->fill([
            'platform' => 'instagram',
            'username' => ltrim($username, '@'),
            'display_name' => $health['displayName'] ?? null,
            'account_type' => $health['accountType'] ?? null,
            'platform_account_id' => $health['platformAccountId'] ?? null,
            'provider_profile_id' => config('zernio.profile_id') ?: null,
            'status' => $health['status'] ?? 'active',
            'token_expires_at' => $health['tokenExpiresAt'] ?? null,
            'provider_payload' => $health,
        ]);

        $account->connected_at ??= now();
        $account->save();

        return $account;
    }

    private function track(SocialAccount $account, string $syncType, array $requestMeta, Closure $callback): array
    {
        $run = $account->syncRuns()->create([
            'provider' => 'zernio',
            'sync_type' => $syncType,
            'status' => 'running',
            'started_at' => now(),
            'request_meta' => $requestMeta,
        ]);

        try {
            $result = $callback();
            $failed = (int) ($result['failed_count'] ?? 0);

            $run->update([
                'status' => $failed > 0 ? 'partial' : 'completed',
                'finished_at' => now(),
                'discovered_count' => (int) ($result['discovered_count'] ?? 0),
                'created_count' => (int) ($result['created_count'] ?? 0),
                'updated_count' => (int) ($result['updated_count'] ?? 0),
                'failed_count' => $failed,
                'error_message' => $failed > 0 ? implode("\n", array_slice($result['errors'] ?? [], 0, 10)) : null,
                'provider_payload' => $result['provider_payload'] ?? null,
            ]);

            return $result;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failed_count' => 1,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function emptyResult(): array
    {
        return [
            'discovered_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'errors' => [],
            'provider_payload' => null,
        ];
    }

    private function rows(array $payload): array
    {
        foreach (['posts', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : [];
            }
        }

        return array_is_list($payload) ? $payload : [];
    }

    private function platformPostId(array $post): ?string
    {
        $value = $post['platformPostId'] ?? data_get($post, 'platform.platformPostId');

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function contentType(array $post): ?string
    {
        $product = strtoupper((string) ($post['mediaProductType'] ?? ''));
        $items = $post['mediaItems'] ?? $post['media'] ?? [];
        $count = is_array($items) ? count($items) : 0;

        if ($product === 'REELS') {
            return 'reel';
        }

        if ($product === 'FEED' && $count > 1) {
            return 'carousel';
        }

        $mediaType = strtolower((string) ($post['mediaType'] ?? ''));

        if ($mediaType !== '') {
            return $mediaType;
        }

        return $product !== '' ? strtolower($product) : null;
    }

    private function instagramPlatformRow(array $row): array
    {
        $platformRows = $row['platformAnalytics'] ?? [];

        if (! is_array($platformRows)) {
            return [];
        }

        foreach ($platformRows as $platformRow) {
            if (is_array($platformRow) && ($platformRow['platform'] ?? null) === 'instagram') {
                return $platformRow;
            }
        }

        return [];
    }

    private function mapAnalyticsStatus(mixed $status): ?string
    {
        return match ($status) {
            'synced' => 'available',
            'pending' => 'pending',
            'unavailable' => 'unavailable',
            'partial' => 'available',
            default => null,
        };
    }

    private function metricTotal(array $payload, string $metric): ?int
    {
        $value = data_get($payload, "metrics.{$metric}");

        if (is_array($value)) {
            $value = $value['total'] ?? $value['value'] ?? null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableInteger(array $data, string $key): ?int
    {
        return array_key_exists($key, $data) && $data[$key] !== null && is_numeric($data[$key])
            ? (int) $data[$key]
            : null;
    }

    private function nullableNumber(array $data, string $key): int|float|null
    {
        return array_key_exists($key, $data) && $data[$key] !== null && is_numeric($data[$key])
            ? (float) $data[$key]
            : null;
    }

    private function configuredAccountId(): string
    {
        $accountId = trim((string) config('zernio.account_id'));

        if ($accountId === '') {
            throw new RuntimeException('ZERNIO_ACCOUNT_ID is not configured.');
        }

        return $accountId;
    }

    private function postAnalyticsRange(?string $fromDate, ?string $toDate): array
    {
        $to = $toDate
            ? CarbonImmutable::parse($toDate, 'UTC')->startOfDay()
            : CarbonImmutable::now('UTC')->startOfDay();
        $from = $fromDate
            ? CarbonImmutable::parse($fromDate, 'UTC')->startOfDay()
            : $to->subDays(365);

        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('The sync start date must be before or equal to the end date.');
        }

        if ($from->diffInDays($to) > 365) {
            $from = $to->subDays(365);
        }

        return [$from, $to];
    }
}
