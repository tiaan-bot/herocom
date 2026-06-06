<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
