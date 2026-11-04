<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ApiUser implements UserInterface
{
    public function __construct(public int $id, public int $accountId, private array $abilities)
    {
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->id;
    }

    public function getRoles(): array
    {
        return ['ROLE_API'];
    }

    public function eraseCredentials(): void
    {
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}
