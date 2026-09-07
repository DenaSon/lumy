<?php

namespace Tests\Feature;

use App\Integrations\Zernio\ZernioClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZernioClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('zernio.base_url', 'https://zernio.com/api/v1');
        config()->set('zernio.api_key', 'test-api-key');
        config()->set('zernio.profile_id', 'profile_123');
    }

    public function test_client_uses_bearer_auth_and_expected_account_health_endpoint(): void
    {
        Http::fake([
            'https://zernio.com/api/v1/accounts/account_123/health' => Http::response([
                'accountId' => 'account_123',
                'status' => 'healthy',
            ]),
        ]);

        $response = app(ZernioClient::class)->accountHealth('account_123');

        $this->assertSame('healthy', $response['status']);

        Http::assertSent(fn (Request $request) =>
            $request->method() === 'GET'
            && $request->url() === 'https://zernio.com/api/v1/accounts/account_123/health'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
        );
    }

    public function test_client_sends_external_post_sync_and_list_contracts(): void
    {
        Http::fake([
            'https://zernio.com/api/v1/posts/sync-external' => Http::response(['synced' => ['postsSynced' => 1]]),
            'https://zernio.com/api/v1/posts*' => Http::response([
                'posts' => [],
                'pagination' => ['page' => 1, 'pages' => 1],
            ]),
        ]);

        $client = app(ZernioClient::class);
        $client->syncExternalPosts('account_123');
        $client->listExternalPosts('account_123', 1, 100);

        Http::assertSent(fn (Request $request) =>
            $request->method() === 'POST'
            && $request->url() === 'https://zernio.com/api/v1/posts/sync-external'
            && $request['accountId'] === 'account_123'
        );

        Http::assertSent(fn (Request $request) =>
            $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://zernio.com/api/v1/posts?')
            && str_contains($request->url(), 'source=external')
            && str_contains($request->url(), 'accountId=account_123')
            && str_contains($request->url(), 'limit=100')
        );
    }
}
