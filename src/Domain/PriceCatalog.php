<?php

declare(strict_types=1);

namespace App\Domain;

interface PriceCatalog
{
    public function price(int $accountId, string $sku): Money;
}
