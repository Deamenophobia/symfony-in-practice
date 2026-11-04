<?php

declare(strict_types=1);

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $row = $this->connection->fetchAssociative('SELECT user_id, account_id, abilities FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()', [hash('sha256', $accessToken)]);
        if (!$row) {
            throw new BadCredentialsException('Invalid access token.');
        }
        $user = new ApiUser((int) $row['user_id'], (int) $row['account_id'], json_decode($row['abilities'], true, flags: JSON_THROW_ON_ERROR));

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
