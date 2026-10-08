<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Exception\LoginException;
use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Login\RedirectTarget;
use Bannerstop\Keycloak\Role\RoleMapper;
use Bannerstop\Keycloak\Session\KeycloakSession;
use Bannerstop\KeycloakBundle\User\UserProvisioner;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What the authenticator does with a login callback.
 *
 * @internal
 */
final readonly class LoginHandler
{
    use TargetPathTrait;

    public const string LOGIN_ROUTE = 'bannerstop_keycloak_login';
    public const string CALLBACK_ROUTE = 'bannerstop_keycloak_callback';
    private const string RETURN_TO = '_bannerstop_keycloak.return_to';

    /**
     * @param \Closure(): LoginFlow $flow Built on first use, so that pages without a Keycloak login work without Keycloak settings
     */
    public function __construct(
        private \Closure $flow,
        private RoleMapper $roleMapper,
        private UserProvisioner $provisioner,
        private SessionTokenStore $tokenStore,
        private UrlGeneratorInterface $urlGenerator,
        private ?LoggerInterface $logger,
        private string $defaultTargetPath,
        private string $failurePath,
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
            $result = ($this->flow)()->finish($request->query->all());
        } catch (LoginException $exception) {
            $this->logger?->notice('Keycloak login failed: {message}', ['message' => $exception->getMessage(), 'reason' => $exception->getReason()->value]);

            throw new CustomUserMessageAuthenticationException('keycloak.login.' . $exception->getReason()->value, [], 0, $exception);
        }

        $identity = $result->getIdentity();
        $user = $this->provisioner->provision($identity, $this->roleMapper->map($identity));
        $this->tokenStore->saveSession($request, KeycloakSession::fromLogin($result, time()));
        $request->attributes->set(self::RETURN_TO, $result->getReturnTo());

        return $user;
    }

    /**
     * Back to the page the login started from: the "_target_path" of the
     * login route, else the page the firewall remembered when it asked for a
     * login (e.g. through a form_login entry point), else the default.
     */
    public function onSuccess(Request $request, string $firewallName): RedirectResponse
    {
        $returnTo = $request->attributes->get(self::RETURN_TO);
        if (is_string($returnTo) && RedirectTarget::isLocal($returnTo)) {
            return new RedirectResponse($returnTo);
        }
        $remembered = $request->hasSession() ? $this->getTargetPath($request->getSession(), $firewallName) : null;
        if (null !== $remembered && str_starts_with($remembered, $request->getSchemeAndHttpHost() . '/')) {
            $this->removeTargetPath($request->getSession(), $firewallName);

            return new RedirectResponse($remembered);
        }

        return new RedirectResponse($this->path($this->defaultTargetPath));
    }

    public function onFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
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
