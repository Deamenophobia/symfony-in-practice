<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Money;
use App\Entity\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainTest extends TestCase
{
    public function testMoneyMultiplicationPreservesTheOriginal(): void
    {
        $price = new Money(1250);
        self::assertSame(2500, $price->times(2)->cents);
        self::assertSame(1250, $price->cents);
    }

    #[DataProvider('invalidQuantities')]
    public function testQuantityBounds(int $quantity): void
    {
        $this->expectException(\DomainException::class);
        (new Money(1250))->times($quantity);
    }

    public static function invalidQuantities(): iterable
    {
        yield [0];
        yield [-1];
        yield [21];
    }

    public function testOverflowIsRejectedBeforeArithmetic(): void
    {
        $this->expectException(\OverflowException::class);
        (new Money(PHP_INT_MAX))->times(2);
    }

    public function testOrderCannotBeConfirmedTwice(): void
    {
        $order = new Order(1, 'COFFEE', 2, new Money(1250));
        $order->confirm();
        $this->expectException(\DomainException::class);
        $order->confirm();
    }
}
