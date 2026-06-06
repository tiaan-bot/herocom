<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Exceptions;

use DomainException;

final class OrderException extends DomainException
{
    public static function emptyCart(): self
    {
        return new self('Your cart has no items available to order.');
    }

    public static function missingCompany(): self
    {
        return new self('Only company users can place orders.');
    }
}
