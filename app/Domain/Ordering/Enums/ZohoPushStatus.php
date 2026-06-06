<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum ZohoPushStatus: string
{
    case Pending = 'pending';
    case Pushed = 'pushed';
    case Failed = 'failed';
}
