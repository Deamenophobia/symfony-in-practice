<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Money
{
    public function __construct(public int $cents, public Currency $currency = Currency::SGD)
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Money cannot be negative.');
        }
    }

    public function times(int $quantity): self
    {
        if ($quantity < 1 || $quantity > 20) {
            throw new \DomainException('Quantity must be between 1 and 20.');
        }
        if ($this->cents > intdiv(PHP_INT_MAX, $quantity)) {
            throw new \OverflowException('Total exceeds the integer range.');
        }

        return new self($this->cents * $quantity, $this->currency);
    }
}
