<?php

namespace App\Integrations\Zernio;

use App\Models\SocialAccount;
use Carbon\CarbonImmutable;

class FollowerBaselineSnapshotSync
{
    public function sync(string $providerAccountId, array $payload): bool
    {
        $account = SocialAccount::query()
            ->where('provider', 'zernio')
            ->where('provider_account_id', $providerAccountId)
            ->first();

        if ($account === null) {
            return false;
        }

        $providerAccount = $this->providerAccount($providerAccountId, $payload);
        $followersCount = $providerAccount['followersCount'] ?? null;
        $followersLastUpdated = $providerAccount['followersLastUpdated'] ?? null;

        if (! is_numeric($followersCount) || ! is_string($followersLastUpdated) || trim($followersLastUpdated) === '') {
            return false;
        }

        $providerUpdatedAt = CarbonImmutable::parse($followersLastUpdated, 'UTC');

        $snapshot = $account->accountMetricSnapshots()->firstOrCreate(
            [
                'snapshot_type' => 'follower_baseline',
                'provider_updated_at' => $providerUpdatedAt,
            ],
            [
                'captured_at' => $providerUpdatedAt,
                'followers_count' => (int) $followersCount,
                'provider_payload' => [
                    'source' => 'zernio_analytics_accounts',
                    'account' => $providerAccount,
                ],
            ],
        );

        return $snapshot->wasRecentlyCreated;
    }

    private function providerAccount(string $providerAccountId, array $payload): array
    {
        $accounts = $payload['accounts'] ?? [];

        if (! is_array($accounts)) {
            return [];
        }

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            if ((string) ($account['_id'] ?? '') === $providerAccountId) {
                return $account;
            }
        }

        return [];
    }
}
