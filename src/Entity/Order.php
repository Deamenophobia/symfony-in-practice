<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Currency;
use App\Domain\Money;
use App\Domain\OrderStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $accountId;

    #[ORM\Column(length: 40)]
    private string $sku;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column]
    private int $unitPriceCents;

    #[ORM\Column]
    private int $totalCents;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference;

    #[ORM\Column(length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Open;

    public function __construct(int $accountId, string $sku, int $quantity, Money $unitPrice, ?string $reference = null)
    {
        if ($accountId < 1 || '' === $sku || strlen($sku) > 40 || (null !== $reference && mb_strlen($reference) > 80)) {
            throw new \DomainException('Invalid order identity or reference.');
        }
        if (Currency::SGD !== $unitPrice->currency) {
            throw new \DomainException('This ordering API accepts SGD prices.');
        }
        $this->accountId = $accountId;
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->unitPriceCents = $unitPrice->cents;
        $this->totalCents = $unitPrice->times($quantity)->cents;
        $this->reference = $reference;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function changeReference(?string $reference): void
    {
        if (null !== $reference && mb_strlen($reference) > 80) {
            throw new \DomainException('Reference is too long.');
        }
        $this->reference = $reference;
    }

    public function confirm(): void
    {
        if (OrderStatus::Open !== $this->status) {
            throw new \DomainException('Only an open order can be confirmed.');
        }
        $this->status = OrderStatus::Confirmed;
    }

    public function view(): array
    {
        return ['id' => $this->id, 'sku' => $this->sku, 'quantity' => $this->quantity,
            'unit_price_cents' => $this->unitPriceCents, 'total_cents' => $this->totalCents,
            'currency' => 'SGD', 'reference' => $this->reference, 'status' => $this->status->value];
    }
}
