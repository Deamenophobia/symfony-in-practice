<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Money;
use App\Domain\PriceCatalog;
use Doctrine\DBAL\Connection;

final readonly class SqlPriceCatalog implements PriceCatalog
{
    public function __construct(private Connection $connection)
    {
    }

    public function price(int $accountId, string $sku): Money
    {
        $price = $this->connection->fetchOne('SELECT price_cents FROM products WHERE account_id = ? AND sku = ?', [$accountId, $sku]);
        if (false === $price) {
            throw new \DomainException('Product is not available to this account.');
        }

        return new Money((int) $price);
    }
}
