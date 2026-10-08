<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Token\TokenSet;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps the tokens of the current login in the session: the ID token is
 * needed for the Keycloak logout, the access token for calls to APIs.
 */
final class SessionTokenStore
{
    private const KEY = '_bannerstop_keycloak.tokens';

    public function save(Request $request, TokenSet $tokens): void
    {
        $request->getSession()->set(self::KEY, $tokens->toArray());
    }

    public function get(Request $request): ?TokenSet
    {
        if (!$request->hasSession()) {
            return null;
        }
        $data = $request->getSession()->get(self::KEY);

        return is_array($data) ? TokenSet::fromArray($data) : null;
    }

    public function clear(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::KEY);
        }
    }
}
