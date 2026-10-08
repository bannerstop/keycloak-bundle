<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Keycloak logout. Runs after the default logout
 * listener (which sets the target response) and before the session is
 * invalidated.
 */
final class LogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LogoutRedirect $redirect,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [LogoutEvent::class => ['onLogout', 32]];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $response = $this->redirect->response($event->getRequest());
        if (null !== $response) {
            $event->setResponse($response);
        }
    }
}
