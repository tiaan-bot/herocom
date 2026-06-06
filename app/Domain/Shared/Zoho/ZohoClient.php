<?php

declare(strict_types=1);

namespace App\Domain\Shared\Zoho;

use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;

final class ZohoClient
{
    private const REFRESH_LOCK = 'zoho:access-token-refresh';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
        private readonly Config $config,
    ) {}

    /**
     * One-time setup: exchange a self-client grant token for a refresh token
     * (+ initial access token) and store them encrypted.
     */
    public function authorize(string $grantToken): ZohoToken
    {
        $payload = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $grantToken,
        ]);

        if (blank($payload['refresh_token'] ?? null)) {
            throw ZohoException::tokenError('missing_refresh_token');
        }

        $token = ZohoToken::query()->first() ?? new ZohoToken;

        $token->forceFill([
            'refresh_token' => $payload['refresh_token'],
            'access_token' => $payload['access_token'] ?? null,
            'access_token_expires_at' => $this->expiryFrom($payload),
            'scopes' => isset($payload['scope'])
                ? explode(',', (string) $payload['scope'])
                : $this->config->get('zoho.scopes'),
        ])->save();

        return $token;
    }

    /**
     * Health check — fetches the configured organization.
     *
     * @return array<string, mixed>
     */
    public function getOrganization(): array
    {
        $orgId = (string) $this->config->get('zoho.organization_id');
        $json = $this->request('GET', "/books/v3/organizations/{$orgId}");

        /** @var array<string, mixed> $org */
        $org = $json['organization'] ?? $json;

        return $org;
    }

    /**
     * @param  array<string, mixed>  $filters  Extra query params (e.g. last_modified_time for incremental sync).
     * @return array<int, array<string, mixed>>
     */
    public function listItems(int $page = 1, array $filters = []): array
    {
        $json = $this->request('GET', '/books/v3/items', array_merge([
            'page' => $page,
            'per_page' => 200,
        ], $filters));

        /** @var array<int, array<string, mixed>> $items */
        $items = $json['items'] ?? [];

        return $items;
    }

    /**
     * Create a Zoho Books contact (customer). Returns the `contact` payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createContact(array $data): array
    {
        $json = $this->request('POST', '/books/v3/contacts', [], $data);

        /** @var array<string, mixed> $contact */
        $contact = $json['contact'] ?? $json;

        return $contact;
    }

    /**
     * Create a Zoho Books sales order. Returns the `salesorder` payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createSalesOrder(array $data): array
    {
        $json = $this->request('POST', '/books/v3/salesorders', [], $data);

        /** @var array<string, mixed> $salesOrder */
        $salesOrder = $json['salesorder'] ?? $json;

        return $salesOrder;
    }

    /**
     * Generic authenticated Books API call. Ensures a valid token, attaches the
     * Authorization header + organization_id, retries on 429/5xx with backoff.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function request(string $method, string $endpoint, array $query = [], array $data = []): array
    {
        $token = $this->accessToken();
        $url = 'https://'.$this->config->get('zoho.api_domain').$endpoint;

        $orgId = $this->config->get('zoho.organization_id');
        if (filled($orgId)) {
            $query = array_merge(['organization_id' => $orgId], $query);
        }

        $options = ['query' => $query];
        if ($data !== []) {
            $options['json'] = $data;
        }

        $response = $this->sendWithRetry(
            fn (): Response => $this->http
                ->timeout((int) $this->config->get('zoho.timeout', 30))
                ->withToken($token, 'Zoho-oauthtoken')
                ->send($method, $url, $options),
        );

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    /**
     * @param  callable(): Response  $attempt
     */
    private function sendWithRetry(callable $attempt): Response
    {
        $max = (int) $this->config->get('zoho.retry.max_attempts', 4);
        $base = (int) $this->config->get('zoho.retry.base_backoff_ms', 1000);

        for ($try = 1; ; $try++) {
            $response = $attempt();

            if ($response->successful()) {
                return $response;
            }

            $retryable = $response->status() === 429 || $response->serverError();

            if (! $retryable || $try >= $max) {
                throw ZohoException::fromResponse($response);
            }

            Sleep::for($this->backoffMs($response, $try, $base))->milliseconds();
        }
    }

    private function backoffMs(Response $response, int $try, int $base): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_numeric($retryAfter)) {
            return (int) ((float) $retryAfter * 1000);
        }

        return $base * (2 ** ($try - 1));
    }

    private function accessToken(): string
    {
        $token = ZohoToken::query()->first();

        if ($token === null || blank($token->refresh_token)) {
            throw ZohoException::notAuthorized();
        }

        if ($token->hasValidAccessToken()) {
            return (string) $token->access_token;
        }

        return $this->refreshAccessToken($token);
    }

    /**
     * Refresh under a cache lock so parallel queued jobs don't double-refresh.
     */
    private function refreshAccessToken(ZohoToken $token): string
    {
        return $this->cache->lock(self::REFRESH_LOCK, 10)->block(10, function () use ($token): string {
            // Another process may have refreshed while we waited for the lock.
            $token->refresh();
            if ($token->hasValidAccessToken()) {
                return (string) $token->access_token;
            }

            $payload = $this->requestToken([
                'grant_type' => 'refresh_token',
                'refresh_token' => $token->refresh_token,
            ]);

            $token->forceFill([
                'access_token' => $payload['access_token'],
                'access_token_expires_at' => $this->expiryFrom($payload),
            ])->save();

            return (string) $payload['access_token'];
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function requestToken(array $params): array
    {
        $url = 'https://'.$this->config->get('zoho.accounts_domain').'/oauth/v2/token';

        $response = $this->http
            ->asForm()
            ->timeout((int) $this->config->get('zoho.timeout', 30))
            ->post($url, array_merge($params, [
                'client_id' => $this->config->get('zoho.client_id'),
                'client_secret' => $this->config->get('zoho.client_secret'),
            ]));

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        if (! $response->successful() || isset($json['error'])) {
            throw ZohoException::tokenError(is_string($json['error'] ?? null) ? $json['error'] : 'http_'.$response->status());
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function expiryFrom(array $payload): ?CarbonImmutable
    {
        if (! isset($payload['expires_in'])) {
            return null;
        }

        $buffer = (int) $this->config->get('zoho.token_expiry_buffer', 60);

        return CarbonImmutable::now()->addSeconds((int) $payload['expires_in'] - $buffer);
    }
}
