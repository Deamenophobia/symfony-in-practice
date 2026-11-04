<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Message\FulfillOrder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

// Registered only in the test container. The production handler has no fault-injection branch.
final readonly class DeliveryProbe implements MiddlewareInterface
{
    public function __construct(private Connection $connection, private bool $failFirst)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        if ($message instanceof FulfillOrder && $envelope->last(ReceivedStamp::class)
            && false !== $this->connection->fetchOne('SELECT id FROM orders WHERE id = ?', [$message->orderId])) {
            $this->connection->executeStatement('INSERT INTO delivery_attempts (order_id, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1', [$message->orderId]);
            $attempts = (int) $this->connection->fetchOne('SELECT attempts FROM delivery_attempts WHERE order_id = ?', [$message->orderId]);
            if ($this->failFirst && 1 === $attempts) {
                throw new \RuntimeException('Synthetic first-delivery failure.');
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
