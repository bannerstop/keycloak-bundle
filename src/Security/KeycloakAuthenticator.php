<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Browser login for the authenticator system (Symfony 5.3 and later).
 */
final class KeycloakAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    /** @var LoginHandler */
    private $handler;

    public function __construct(LoginHandler $handler)
    {
        $this->handler = $handler;
    }

    public function supports(Request $request): ?bool
    {
        return $this->handler->isCallback($request);
    }

    public function authenticate(Request $request): Passport
    {
        $user = $this->handler->finish($request);

        // The badge only takes effect if the firewall has remember_me configured.
        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static function () use ($user) {
            return $user;
        }), [new RememberMeBadge()]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->handler->onSuccess($request, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->handler->onFailure($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->handler->start($request);
    }
}
