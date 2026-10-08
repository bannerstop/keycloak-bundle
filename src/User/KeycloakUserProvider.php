<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * User provider for KeycloakUser. The user is kept in the session as it was
 * at login, so there is nothing to load by identifier.
 */
final class KeycloakUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $exception = new UserNotFoundException('KeycloakUser objects cannot be loaded by identifier.');
        $exception->setUserIdentifier($identifier);

        throw $exception;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof KeycloakUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return KeycloakUser::class === $class;
    }
}
