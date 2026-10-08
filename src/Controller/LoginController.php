<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Controller;

use Bannerstop\Keycloak\Login\LoginFlow;
use Bannerstop\Keycloak\Login\RedirectTarget;
use Bannerstop\KeycloakBundle\Security\LoginHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LoginController
{
    /**
     * @param array<string, string> $authorizationParameters
     */
    public function __construct(
        private LoginFlow $flow,
        private UrlGeneratorInterface $urlGenerator,
        private array $authorizationParameters,
    ) {
    }

    /**
     * Starts the login. Pass "_target_path" to come back to a page afterwards.
     */
    public function start(Request $request): RedirectResponse
    {
        $target = $request->query->get('_target_path');

        return new RedirectResponse($this->flow->start(
            $this->urlGenerator->generate(LoginHandler::CALLBACK_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL),
            is_string($target) && RedirectTarget::isLocal($target) ? $target : null,
            $this->authorizationParameters
        ));
    }

    public function callback(): never
    {
        throw new \LogicException('The Keycloak callback must be handled by the bundle\'s authenticator. Add it to the firewall that covers this route.');
    }
}
