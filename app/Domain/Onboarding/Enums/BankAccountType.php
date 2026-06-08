<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

/**
 * Bank account type captured in the credit application's banking section.
 * Stored inside the encrypted cgic_payload, not as a column.
 */
enum BankAccountType: string
{
    case Cheque = 'cheque';
    case Savings = 'savings';
    case Transmission = 'transmission';
}
