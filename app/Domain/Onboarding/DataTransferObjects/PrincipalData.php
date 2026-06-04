<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\DataTransferObjects;

final readonly class PrincipalData
{
    public function __construct(
        public string $fullName,
        public string $surname,
        public string $idNumber,
        public ?float $shareholdingPercent = null,
        public ?string $residentialAddressLine1 = null,
        public ?string $residentialAddressLine2 = null,
        public ?string $residentialCity = null,
        public ?string $residentialProvince = null,
        public ?string $residentialPostalCode = null,
        public string $countryCode = 'ZA',
        public bool $isSurety = true,
        public ?bool $marriedInCommunity = null,
    ) {}
}
