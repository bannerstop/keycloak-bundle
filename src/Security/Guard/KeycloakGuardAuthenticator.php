<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security\Guard;

use Bannerstop\KeycloakBundle\Security\LoginHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

/**
 * Browser login for Guard (Symfony 4.4, and 5.x without the authenticator manager).
 */
final class KeycloakGuardAuthenticator extends AbstractGuardAuthenticator
{
    /** @var LoginHandler */
    private $handler;

    public function __construct(LoginHandler $handler)
    {
        $this->handler = $handler;
    }

    public function supports(Request $request): bool
    {
        return $this->handler->isCallback($request);
    }

    /**
     * @return Request
     */
    public function getCredentials(Request $request)
    {
        return $request;
    }

    /**
     * @param Request $credentials
     */
    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        return $this->handler->finish($credentials);
    }

    /**
     * @param Request $credentials
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
        return $this->handler->onSuccess($request, (string) $providerKey);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->handler->onFailure($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->handler->start($request);
    }

    /**
     * Only takes effect if the firewall has remember_me configured.
     */
    public function supportsRememberMe(): bool
    {
        return true;
    }
}
