<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum StockBand: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public static function for(float $stockOnHand, int $lowStockThreshold): self
    {
        return match (true) {
            $stockOnHand <= 0 => self::OutOfStock,
            $stockOnHand <= $lowStockThreshold => self::LowStock,
            default => self::InStock,
        };
    }
}
