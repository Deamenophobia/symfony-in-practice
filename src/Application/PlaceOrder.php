<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\PriceCatalog;
use App\Dto\CreateOrder;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PlaceOrder
{
    public function __construct(private PriceCatalog $catalog, private EntityManagerInterface $entities)
    {
    }

    public function place(int $accountId, CreateOrder $input): Order
    {
        return $this->entities->wrapInTransaction(function () use ($accountId, $input): Order {
            $order = new Order($accountId, $input->sku, $input->quantity,
                $this->catalog->price($accountId, $input->sku), $input->reference);
            $this->entities->persist($order);

            return $order;
        });
    }
}
