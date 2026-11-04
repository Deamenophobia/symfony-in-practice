<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Domain\PriceCatalog;

final readonly class Quote
{
    public function __construct(private PriceCatalog $catalog)
    {
    }

    public function total(): int
    {
        return $this->catalog->price(1, 'COFFEE')->times(2)->cents;
    }
}
