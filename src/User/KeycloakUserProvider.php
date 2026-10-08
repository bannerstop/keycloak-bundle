<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\User;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
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
        if (class_exists(UserNotFoundException::class)) {
            $exception = new UserNotFoundException('KeycloakUser objects cannot be loaded by identifier.');
            $exception->setUserIdentifier($identifier);
        } else {
            $exception = new UsernameNotFoundException('KeycloakUser objects cannot be loaded by identifier.');
            $exception->setUsername($identifier);
        }

        throw $exception;
    }

    /**
     * @param string $username
     */
    public function loadUserByUsername($username): UserInterface
    {
        return $this->loadUserByIdentifier((string) $username);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof KeycloakUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', get_class($user)));
        }

        return $user;
    }

    /**
     * @param string $class
     */
    public function supportsClass($class): bool
    {
        return KeycloakUser::class === $class;
    }
}
