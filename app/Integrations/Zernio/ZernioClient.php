<?php

namespace App\Integrations\Zernio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZernioClient
{
    public function accountHealth(string $accountId): array
    {
        return $this->get("accounts/{$accountId}/health");
    }

    public function syncExternalPosts(string $accountId, ?string $url = null, ?string $postId = null): array
    {
        return $this->post('posts/sync-external', array_filter([
            'accountId' => $accountId,
            'url' => $url,
            'postId' => $postId,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function listExternalPosts(string $accountId, int $page = 1, int $limit = 100): array
    {
        return $this->get('posts', [
            'source' => 'external',
            'accountId' => $accountId,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    public function analytics(array $query = []): array
    {
        return $this->get('analytics', $query);
    }

    public function instagramAccountInsights(
        string $accountId,
        string $since,
        string $until,
        array $metrics = [],
    ): array {
        return $this->get('analytics/instagram/account-insights', array_filter([
            'accountId' => $accountId,
            'since' => $since,
            'until' => $until,
            'metrics' => $metrics === [] ? null : implode(',', $metrics),
            'metricType' => 'total_value',
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function instagramDemographics(
        string $accountId,
        string $metric = 'follower_demographics',
        array $breakdowns = ['age', 'city', 'country', 'gender'],
        string $timeframe = 'this_month',
    ): array {
        return $this->get('analytics/instagram/demographics', [
            'accountId' => $accountId,
            'metric' => $metric,
            'breakdown' => implode(',', $breakdowns),
            'timeframe' => $timeframe,
        ]);
    }

    public function instagramFollowerHistory(
        string $accountId,
        string $since,
        string $until,
        array $metrics = ['follower_count', 'followers_gained', 'followers_lost'],
    ): array {
        return $this->get('analytics/instagram/follower-history', [
            'accountId' => $accountId,
            'metrics' => implode(',', $metrics),
            'since' => $since,
            'until' => $until,
            'metricType' => 'time_series',
        ]);
    }

    public function analyticsDelta(?string $cursor = null, int $limit = 50): array
    {
        return $this->get('analytics/delta', array_filter([
            'cursor' => $cursor,
            'limit' => $limit,
            'platform' => 'instagram',
            'profileId' => config('zernio.profile_id') ?: null,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    private function get(string $path, array $query = []): array
    {
        $data = $this->request()->get($path, $query)->throw()->json();

        return is_array($data) ? $data : [];
    }

    private function post(string $path, array $payload = []): array
    {
        $data = $this->request()->post($path, $payload)->throw()->json();

        return is_array($data) ? $data : [];
    }

    private function request(): PendingRequest
    {
        $apiKey = trim((string) config('zernio.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('ZERNIO_API_KEY is not configured.');
        }

        $baseUrl = rtrim((string) config('zernio.base_url', 'https://zernio.com/api/v1'), '/');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout((int) config('zernio.timeout', 30));
    }
}
