<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Session\KeycloakSession;
use Bannerstop\Keycloak\Token\TokenSet;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps the Keycloak session behind the current login in the session: the
 * ID token for the Keycloak logout, the refresh token and the Keycloak
 * session id for the session check, the access token for calls to APIs.
 */
final class SessionTokenStore
{
    private const KEY = '_bannerstop_keycloak.session';

    /** Written by versions before 3.3, read for sessions that started before an update. */
    private const LEGACY_TOKENS_KEY = '_bannerstop_keycloak.tokens';

    public function saveSession(Request $request, KeycloakSession $session): void
    {
        $request->getSession()->set(self::KEY, $session->toArray());
        $request->getSession()->remove(self::LEGACY_TOKENS_KEY);
    }

    public function getSession(Request $request): ?KeycloakSession
    {
        if (!$request->hasSession()) {
            return null;
        }
        $data = $request->getSession()->get(self::KEY);

        return is_array($data) ? KeycloakSession::fromArray($data) : null;
    }

    public function get(Request $request): ?TokenSet
    {
        $session = $this->getSession($request);
        if (null !== $session) {
            return $session->getTokens();
        }
        $data = $request->hasSession() ? $request->getSession()->get(self::LEGACY_TOKENS_KEY) : null;

        return is_array($data) ? TokenSet::fromArray($data) : null;
    }

    public function clear(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::KEY);
            $request->getSession()->remove(self::LEGACY_TOKENS_KEY);
        }
    }
}
