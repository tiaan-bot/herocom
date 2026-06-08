<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\DataTransferObjects;

use App\Domain\Onboarding\Enums\AccountHeld;

final readonly class TradeReferenceData
{
    public function __construct(
        public string $companyName,
        public ?float $creditLimit = null,
        public AccountHeld $accountHeld = AccountHeld::Credit,
        public ?int $termsDays = null,
        public string $creditLimitCurrency = 'ZAR',
    ) {}
}
