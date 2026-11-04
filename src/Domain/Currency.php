<?php

declare(strict_types=1);

namespace App\Domain;

enum Currency: string
{
    case SGD = 'SGD';
    case USD = 'USD';
}
