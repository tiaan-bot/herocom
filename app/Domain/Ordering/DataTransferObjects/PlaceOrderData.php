<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DataTransferObjects;

final readonly class PlaceOrderData
{
    public function __construct(
        public string $deliveryAddressLine1,
        public string $deliveryCity,
        public string $deliveryProvince,
        public string $deliveryPostalCode,
        public ?string $deliveryAddressLine2 = null,
        public string $deliveryCountryCode = 'ZA',
        public ?string $customerNote = null,
    ) {}
}
