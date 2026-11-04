<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Domain\Money;
use App\Domain\PriceCatalog;

final class FixedCatalog implements PriceCatalog
{
    public function price(int $accountId, string $sku): Money
    {
        return new Money(1250);
    }
}
