<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Exception\HttpException;
use Bannerstop\Keycloak\KeycloakClient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ends the Keycloak session together with the local one. Without it, the
 * next login would silently succeed with the still open Keycloak session.
 *
 * @internal
 */
final class LogoutRedirect
{
    /**
     * @param \Closure(): KeycloakClient $client Built on first use: most logouts in tests and local setups never reach Keycloak
     */
    public function __construct(
        private readonly \Closure $client,
        private readonly SessionTokenStore $tokenStore,
        private readonly LoginHandler $loginHandler,
        private readonly string $logoutTarget,
    ) {
    }

    /**
     * Null if the session was not a Keycloak login.
     */
    public function response(Request $request): ?RedirectResponse
    {
        $tokens = $this->tokenStore->get($request);
        if (null === $tokens) {
            return null;
        }
        $this->tokenStore->clear($request);
        try {
            $url = ($this->client)()->getLogoutUrl($this->loginHandler->absoluteUrl($request, $this->logoutTarget), $tokens->getIdToken());
        } catch (HttpException) {
            return null;
        }

        return null === $url ? null : new RedirectResponse($url);
    }

    public function fallback(): RedirectResponse
    {
        return new RedirectResponse($this->loginHandler->path($this->logoutTarget));
    }
}
