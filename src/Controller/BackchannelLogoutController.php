<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Controller;

use Bannerstop\Keycloak\Exception\KeycloakException;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\Session\SessionRevocations;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keycloak's back-channel logout call (OpenID Connect Back-Channel Logout
 * 1.0): server to server, without session or CSRF token. The ended Keycloak
 * session is recorded; SessionCheckListener ends the matching application
 * sessions on their next request.
 */
final class BackchannelLogoutController
{
    /** @var \Closure */
    private $client;

    /** @var SessionRevocations|null */
    private $revocations;

    /** @var LoggerInterface|null */
    private $logger;

    /**
     * @param \Closure(): KeycloakClient $client Built on first use, see LoginHandler
     */
    public function __construct(\Closure $client, ?SessionRevocations $revocations, ?LoggerInterface $logger)
    {
        $this->client = $client;
        $this->revocations = $revocations;
        $this->logger = $logger;
    }

    public function __invoke(Request $request): Response
    {
        if (null === $this->revocations) {
            if (null !== $this->logger) {
                $this->logger->error('Keycloak back-channel logout needs a cache (bannerstop_keycloak.cache), none is configured.');
            }

            return self::response(Response::HTTP_NOT_IMPLEMENTED);
        }
        $logoutToken = $request->request->get('logout_token');
        if (!is_string($logoutToken) || '' === $logoutToken) {
            return self::response(Response::HTTP_BAD_REQUEST);
        }

        try {
            $accepted = $this->revocations->revoke(($this->client)()->verifyLogoutToken($logoutToken));
        } catch (KeycloakException $exception) {
            if (null !== $this->logger) {
                $this->logger->warning('Keycloak back-channel logout rejected: {message}', ['message' => $exception->getMessage()]);
            }

            return self::response(Response::HTTP_BAD_REQUEST);
        }
        if (!$accepted) {
            if (null !== $this->logger) {
                $this->logger->warning('Keycloak back-channel logout rejected: the logout token was replayed.');
            }
        }

        return self::response($accepted ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    private static function response(int $status): Response
    {
        return new Response('', $status, ['Cache-Control' => 'no-store']);
    }
}
