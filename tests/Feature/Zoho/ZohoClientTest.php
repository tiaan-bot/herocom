<?php

declare(strict_types=1);

use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use App\Domain\Shared\Zoho\ZohoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'zoho.client_id' => 'cid',
        'zoho.client_secret' => 'secret',
        'zoho.organization_id' => 'org-123',
        'zoho.accounts_domain' => 'accounts.zoho.com',
        'zoho.api_domain' => 'www.zohoapis.com',
        'zoho.retry.max_attempts' => 4,
        'zoho.retry.base_backoff_ms' => 10,
        'zoho.token_expiry_buffer' => 60,
    ]);
});

function zoho(): ZohoClient
{
    return app(ZohoClient::class);
}

function validToken(): ZohoToken
{
    return ZohoToken::query()->create([
        'refresh_token' => 'refresh-abc',
        'access_token' => 'valid-access',
        'access_token_expires_at' => now()->addHour(),
    ]);
}

it('refreshes the access token when missing and persists it', function () {
    ZohoToken::query()->create(['refresh_token' => 'refresh-abc']);

    Http::fake([
        '*/oauth/v2/token' => Http::response(['access_token' => 'access-new', 'expires_in' => 3600]),
        '*/books/v3/items*' => Http::response(['items' => [['name' => 'Widget', 'rate' => 10]]]),
    ]);

    $items = zoho()->listItems();

    expect($items)->toHaveCount(1);

    $token = ZohoToken::query()->first();
    expect($token->access_token)->toBe('access-new')
        ->and($token->access_token_expires_at->isFuture())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/v2/token'));
});

it('attaches the Zoho-oauthtoken header and organization_id', function () {
    validToken();
    Http::fake(['*/books/v3/*' => Http::response(['organization' => ['name' => 'Herocom', 'organization_id' => 'org-123']])]);

    zoho()->getOrganization();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Zoho-oauthtoken valid-access')
        && str_contains($request->url(), '/books/v3/organizations/org-123')
        && str_contains($request->url(), 'organization_id=org-123'));
});

it('retries on 429 honouring Retry-After then succeeds', function () {
    Sleep::fake();
    validToken();
    Http::fake([
        '*/books/v3/items*' => Http::sequence()
            ->push('', 429, ['Retry-After' => '2'])
            ->push(['items' => []], 200),
    ]);

    zoho()->listItems();

    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 2000);
});

it('retries on server errors with backoff', function () {
    Sleep::fake();
    validToken();
    Http::fake([
        '*/books/v3/items*' => Http::sequence()
            ->push('', 500)
            ->push('', 503)
            ->push(['items' => []], 200),
    ]);

    zoho()->listItems();

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('throws a ZohoException on a non-retryable error', function () {
    validToken();
    Http::fake(['*/books/v3/items*' => Http::response(['message' => 'Unauthorized'], 401)]);

    zoho()->listItems();
})->throws(ZohoException::class);

it('throws when not authorized', function () {
    zoho()->listItems();
})->throws(ZohoException::class);

it('stores an encrypted refresh token from the grant exchange', function () {
    Http::fake(['*/oauth/v2/token' => Http::response([
        'refresh_token' => 'refresh-xyz',
        'access_token' => 'access-1',
        'expires_in' => 3600,
        'scope' => 'ZohoBooks.items.READ',
    ])]);

    zoho()->authorize('grant-123');

    $token = ZohoToken::query()->first();
    expect($token->refresh_token)->toBe('refresh-xyz')
        ->and($token->access_token)->toBe('access-1')
        ->and($token->scopes)->toBe(['ZohoBooks.items.READ']);

    // Encrypted at rest.
    $raw = DB::table('zoho_tokens')->value('refresh_token');
    expect($raw)->not->toBe('refresh-xyz')
        ->and(Crypt::decryptString($raw))->toBe('refresh-xyz');

    Http::assertSent(fn ($request) => str_contains((string) $request->body(), 'grant_type=authorization_code')
        && str_contains((string) $request->body(), 'code=grant-123'));
});
