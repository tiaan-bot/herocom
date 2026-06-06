<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum CartStatus: string
{
    case Open = 'open';
    case Converted = 'converted';
    case Abandoned = 'abandoned';
}
