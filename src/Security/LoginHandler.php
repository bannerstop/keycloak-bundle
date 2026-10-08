<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Exception\LoginException;
use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Login\RedirectTarget;
use Bannerstop\Keycloak\Role\RoleMapper;
use Bannerstop\KeycloakBundle\User\UserProvisioner;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What the authenticator does with a login callback.
 *
 * @internal
 */
final class LoginHandler
{
    public const LOGIN_ROUTE = 'bannerstop_keycloak_login';
    public const CALLBACK_ROUTE = 'bannerstop_keycloak_callback';
    private const RETURN_TO = '_bannerstop_keycloak.return_to';

    public function __construct(
        private readonly LoginFlow $flow,
        private readonly RoleMapper $roleMapper,
        private readonly UserProvisioner $provisioner,
        private readonly SessionTokenStore $tokenStore,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?LoggerInterface $logger,
        private readonly string $defaultTargetPath,
        private readonly string $failurePath,
    ) {
    }

    public function isCallback(Request $request): bool
    {
        return self::CALLBACK_ROUTE === $request->attributes->get('_route');
    }

    /**
     * @throws AuthenticationException
     */
    public function finish(Request $request): UserInterface
    {
        try {
            $result = $this->flow->finish($request->query->all());
        } catch (LoginException $exception) {
            $this->logger?->notice('Keycloak login failed: {message}', ['message' => $exception->getMessage(), 'reason' => $exception->getReason()->value]);

            throw new CustomUserMessageAuthenticationException('keycloak.login.' . $exception->getReason()->value, [], 0, $exception);
        }

        $identity = $result->getIdentity();
        $user = $this->provisioner->provision($identity, $this->roleMapper->map($identity));
        $this->tokenStore->save($request, $result->getTokens());
        $request->attributes->set(self::RETURN_TO, $result->getReturnTo());

        return $user;
    }

    public function onSuccess(Request $request): RedirectResponse
    {
        $returnTo = $request->attributes->get(self::RETURN_TO);

        return new RedirectResponse(is_string($returnTo) && RedirectTarget::isLocal($returnTo) ? $returnTo : $this->path($this->defaultTargetPath));
    }

    public function onFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        if ($request->hasSession()) {
            // SecurityRequestAttributes replaced Security in Symfony 6.2
            $key = class_exists(SecurityRequestAttributes::class) ? SecurityRequestAttributes::AUTHENTICATION_ERROR : Security::AUTHENTICATION_ERROR;
            $request->getSession()->set($key, $exception);
        }

        return new RedirectResponse($this->path($this->failurePath));
    }

    /**
     * Entry point: sends anonymous users to the Keycloak login and back to
     * the page they asked for.
     */
    public function start(Request $request): RedirectResponse
    {
        $parameters = [];
        if ($request->isMethodSafe() && !$request->isXmlHttpRequest()) {
            $parameters['_target_path'] = $request->getRequestUri();
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE, $parameters));
    }

    /**
     * A configured target: a path ("/dashboard"), an absolute URL or a route name.
     */
    public function path(string $target): string
    {
        return self::isRouteName($target) ? $this->urlGenerator->generate($target) : $target;
    }

    public function absoluteUrl(Request $request, string $target): string
    {
        if (self::isRouteName($target)) {
            return $this->urlGenerator->generate($target, [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return RedirectTarget::isLocal($target) ? $request->getUriForPath($target) : $target;
    }

    private static function isRouteName(string $target): bool
    {
        return !RedirectTarget::isLocal($target) && false === filter_var($target, FILTER_VALIDATE_URL);
    }
}
