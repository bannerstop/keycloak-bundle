<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Login\PendingLogin;
use Bannerstop\Keycloak\Login\StateStore;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Pending logins in the Symfony session, at most five at a time.
 */
final class SessionStateStore implements StateStore
{
    private const KEY = '_bannerstop_keycloak.logins';
    private const MAX_PENDING = 5;

    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function save(PendingLogin $login): void
    {
        $session = $this->session();
        $pending = $this->all($session);
        $pending[$login->getState()] = $login->toArray();
        $session->set(self::KEY, array_slice($pending, -self::MAX_PENDING, null, true));
    }

    public function take(string $state): ?PendingLogin
    {
        $session = $this->session();
        $pending = $this->all($session);
        $data = $pending[$state] ?? null;
        unset($pending[$state]);
        $session->set(self::KEY, $pending);

        return is_array($data) ? PendingLogin::fromArray($data) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function all(SessionInterface $session): array
    {
        $pending = $session->get(self::KEY, []);

        return is_array($pending) ? $pending : [];
    }

    private function session(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            throw new \LogicException('The Keycloak login needs a session.');
        }

        return $request->getSession();
    }
}
