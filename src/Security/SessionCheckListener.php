<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Session\SessionCheck;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Ends an application session when its Keycloak session has ended: revoked
 * through the back channel, or refused at the periodic refresh check. Runs
 * right after the firewall, only for sessions that came from a Keycloak login.
 */
final class SessionCheckListener implements EventSubscriberInterface
{
    /** After the firewall (8), so that a logout replaces the firewall's work of this request. */
    private const PRIORITY = 7;

    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    /**
     * @param \Closure(): SessionCheck $check Built on first use, see LoginHandler
     */
    public function __construct(
        private readonly \Closure $check,
        private readonly SessionTokenStore $tokenStore,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?object $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', self::PRIORITY]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || str_starts_with((string) $request->attributes->get('_route'), 'bannerstop_keycloak_')) {
            return;
        }
        $session = $this->tokenStore->getSession($request);
        if (null === $session) {
            return;
        }

        $checked = ($this->check)()->check($session);
        if (null !== $checked) {
            if ($checked !== $session) {
                $this->tokenStore->saveSession($request, $checked);
            }

            return;
        }

        $this->endSession($request);
        $response = $this->response($request);
        // The remember-me cookie would log the user straight back in. "REMEMBERME" is Symfony's default cookie name.
        $response->headers->clearCookie(self::REMEMBER_ME_COOKIE);
        $event->setResponse($response);
    }

    private function endSession(Request $request): void
    {
        // Without tokens, the logout does not send the user to Keycloak, whose session is gone already.
        $this->tokenStore->clear($request);
        // Security::logout() (Symfony 6.2+) also runs the firewall's logout listeners; older versions do not have it.
        if (null !== $this->security && method_exists($this->security, 'logout')) {
            try {
                $this->security->logout(false);
            } catch (\LogicException) {
                // nobody logged in at the moment, e.g. a lazy firewall that has not loaded the user yet
            }
        }
        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();
    }

    private function response(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat()) {
            return new JsonResponse(['error' => 'session_ended'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($request->isMethodSafe() ? $request->getUri() : $this->urlGenerator->generate(LoginHandler::LOGIN_ROUTE));
    }
}
