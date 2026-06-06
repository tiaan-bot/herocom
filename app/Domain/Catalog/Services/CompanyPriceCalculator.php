<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

/**
 * Single home for catalog pricing: the company-specific price is the Zoho list
 * rate less the company's flat discount_percent, computed at render (never
 * stored, never client-trusted). The Phase 3 tier engine replaces only this class.
 */
final class CompanyPriceCalculator
{
    public function netPrice(float $listRate, float $discountPercent): float
    {
        $discount = max(0.0, min(100.0, $discountPercent));

        return round($listRate * (1 - $discount / 100), 2);
    }
}
