<?php

namespace App\Integrations\Zernio;

class BaselineAwareZernioClient extends ZernioClient
{
    public function __construct(private readonly FollowerBaselineSnapshotSync $followerBaseline) {}

    public function analytics(array $query = []): array
    {
        $payload = parent::analytics($query);
        $accountId = $query['accountId'] ?? null;

        if (is_string($accountId) && $accountId !== '') {
            $this->followerBaseline->sync($accountId, $payload);
        }

        return $payload;
    }
}
