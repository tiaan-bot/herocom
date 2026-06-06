<?php

declare(strict_types=1);

use App\Domain\Catalog\Services\CompanyPriceCalculator;

it('applies the company discount', function () {
    expect((new CompanyPriceCalculator)->netPrice(100.0, 10.0))->toBe(90.0);
});

it('returns the list price at zero discount', function () {
    expect((new CompanyPriceCalculator)->netPrice(100.0, 0.0))->toBe(100.0);
});

it('rounds to two decimals', function () {
    expect((new CompanyPriceCalculator)->netPrice(99.99, 15.0))->toBe(84.99);
});

it('clamps discount to the 0–100 range', function () {
    $calc = new CompanyPriceCalculator;
    expect($calc->netPrice(100.0, -5.0))->toBe(100.0)
        ->and($calc->netPrice(100.0, 150.0))->toBe(0.0);
});
