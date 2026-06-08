<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

/**
 * Generation state of the system-built application-form PDF (Stream B2).
 */
enum ApplicationPdfStatus: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Failed = 'failed';
}
