<?php

declare(strict_types=1);

namespace App\Domain\Shared\Zoho\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

final class ZohoException extends RuntimeException
{
    public static function notAuthorized(): self
    {
        return new self('Zoho is not authorized. Run `php artisan zoho:authorize <grantToken>` first.');
    }

    public static function tokenError(string $code): self
    {
        // $code is a Zoho error code (e.g. "invalid_code") — never a token value.
        return new self("Zoho token request failed: {$code}.");
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $detail = is_array($body)
            ? (string) ($body['message'] ?? $body['code'] ?? '')
            : '';

        return new self(trim("Zoho API request failed (HTTP {$response->status()}). {$detail}"));
    }
}
