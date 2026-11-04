<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Order;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class OrderVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return 'ORDER_VIEW' === $attribute && $subject instanceof Order;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof ApiUser && $user->can('orders:read') && $user->accountId === $subject->accountId();
    }
}
