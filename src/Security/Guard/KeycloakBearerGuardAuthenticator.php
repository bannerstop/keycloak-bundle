<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security\Guard;

use Bannerstop\KeycloakBundle\Security\BearerHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

/**
 * Access tokens in the Authorization header for Guard (Symfony 4.4, and 5.x
 * without the authenticator manager).
 */
final class KeycloakBearerGuardAuthenticator extends AbstractGuardAuthenticator
{
    private BearerHandler $handler;

    public function __construct(BearerHandler $handler)
    {
        $this->handler = $handler;
    }

    public function supports(Request $request): bool
    {
        return null !== $this->handler->token($request);
    }

    /**
     * @return string
     */
    public function getCredentials(Request $request)
    {
        return (string) $this->handler->token($request);
    }

    /**
     * @param string $credentials
     */
    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        return $this->handler->authenticate($credentials);
    }

    /**
     * @param string $credentials
     */
    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true;
    }

    /**
     * @param string $providerKey
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->handler->challenge($exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->handler->challenge(null);
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}
