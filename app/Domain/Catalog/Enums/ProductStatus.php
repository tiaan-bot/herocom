<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
