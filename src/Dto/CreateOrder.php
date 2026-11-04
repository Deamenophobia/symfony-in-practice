<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateOrder
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public string $sku,
        #[Assert\Range(min: 1, max: 20)]
        public int $quantity,
        #[Assert\Length(max: 80)]
        public ?string $reference = null,
    ) {
    }
}
