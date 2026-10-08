<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Bearer\BearerToken;
use Bannerstop\Keycloak\Exception\HttpException;
use Bannerstop\Keycloak\Exception\InvalidTokenException;
use Bannerstop\Keycloak\KeycloakClient;
use Bannerstop\Keycloak\Role\RoleMapper;
use Bannerstop\KeycloakBundle\User\UserProvisioner;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What both bearer authenticator flavours do with an API request.
 *
 * @internal
 */
final class BearerHandler
{
    /** @var \Closure(): KeycloakClient Built on first use, see LoginHandler */
    private $client;

    /** @var RoleMapper */
    private $roleMapper;

    /** @var UserProvisioner */
    private $provisioner;

    /** @var string|null */
    private $audience;

    /**
     * @param \Closure(): KeycloakClient $client Built on first use, see LoginHandler
     */
    public function __construct(\Closure $client, RoleMapper $roleMapper, UserProvisioner $provisioner, ?string $audience)
    {
        $this->client = $client;
        $this->roleMapper = $roleMapper;
        $this->provisioner = $provisioner;
        $this->audience = $audience;
    }

    public function token(Request $request): ?string
    {
        return BearerToken::fromAuthorizationHeader($request->headers->get('Authorization'));
    }

    /**
     * @throws AuthenticationException
     */
    public function authenticate(string $token): UserInterface
    {
        try {
            $identity = ($this->client)()->verifyAccessToken($token, $this->audience);
        } catch (InvalidTokenException $exception) {
            throw new CustomUserMessageAuthenticationException('The access token is invalid.', [], 0, $exception);
        } catch (HttpException $exception) {
            throw new CustomUserMessageAuthenticationException('The access token cannot be verified right now.', [], 0, $exception);
        }

        return $this->provisioner->provision($identity, $this->roleMapper->map($identity));
    }

    /**
     * RFC 6750 3: 401 with a WWW-Authenticate challenge.
     */
    public function challenge(?AuthenticationException $exception): JsonResponse
    {
        $header = 'Bearer';
        $body = ['error' => 'unauthorized'];
        if (null !== $exception) {
            $header .= ' error="invalid_token"';
            $body = ['error' => 'invalid_token', 'error_description' => $exception->getMessageKey()];
        }

        return new JsonResponse($body, 401, ['WWW-Authenticate' => $header]);
    }
}
