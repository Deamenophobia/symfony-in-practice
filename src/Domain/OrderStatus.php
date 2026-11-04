<?php

declare(strict_types=1);

namespace App\Domain;

enum OrderStatus: string
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
