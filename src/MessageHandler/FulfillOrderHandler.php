<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\FulfillOrder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FulfillOrderHandler
{
    public function __construct(private Connection $connection)
    {
    }

    public function __invoke(FulfillOrder $message): void
    {
        $this->connection->executeStatement('INSERT INTO fulfillment_tasks (order_id) VALUES (?) ON DUPLICATE KEY UPDATE order_id = order_id', [$message->orderId]);
    }
}
