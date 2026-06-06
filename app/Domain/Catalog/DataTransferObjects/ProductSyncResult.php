<?php

declare(strict_types=1);

namespace App\Domain\Catalog\DataTransferObjects;

final readonly class ProductSyncResult
{
    public function __construct(
        public int $synced,
        public int $deactivated,
    ) {}
}
