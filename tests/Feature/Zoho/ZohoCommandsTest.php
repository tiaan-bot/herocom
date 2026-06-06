<?php

declare(strict_types=1);

use App\Domain\Shared\Zoho\Models\ZohoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'zoho.client_id' => 'cid',
        'zoho.client_secret' => 'secret',
        'zoho.organization_id' => 'org-123',
        'zoho.accounts_domain' => 'accounts.zoho.com',
        'zoho.api_domain' => 'www.zohoapis.com',
    ]);
});

it('zoho:authorize exchanges a grant token and stores the refresh token', function () {
    Http::fake(['*/oauth/v2/token' => Http::response([
        'refresh_token' => 'r-cmd',
        'access_token' => 'a-cmd',
        'expires_in' => 3600,
        'scope' => 'ZohoBooks.items.READ',
    ])]);

    $this->artisan('zoho:authorize', ['grantToken' => 'grant-1'])->assertSuccessful();

    expect(ZohoToken::query()->count())->toBe(1)
        ->and(ZohoToken::query()->first()->refresh_token)->toBe('r-cmd');
});

it('zoho:authorize fails cleanly on a token error', function () {
    Http::fake(['*/oauth/v2/token' => Http::response(['error' => 'invalid_code'], 400)]);

    $this->artisan('zoho:authorize', ['grantToken' => 'bad'])->assertFailed();

    expect(ZohoToken::query()->count())->toBe(0);
});

it('zoho:ping reports the organization and items', function () {
    ZohoToken::query()->create([
        'refresh_token' => 'r',
        'access_token' => 'valid',
        'access_token_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        '*/books/v3/organizations*' => Http::response(['organization' => ['name' => 'Herocom Distribution', 'organization_id' => 'org-123']]),
        '*/books/v3/items*' => Http::response(['items' => [['name' => 'Widget', 'rate' => '99.00']]]),
    ]);

    $this->artisan('zoho:ping')
        ->assertSuccessful()
        ->expectsOutputToContain('Herocom Distribution')
        ->expectsOutputToContain('Widget');
});
