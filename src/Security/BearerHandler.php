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
 * What the bearer authenticator does with an API request.
 *
 * @internal
 */
final readonly class BearerHandler
{
    public function __construct(
        private KeycloakClient $client,
        private RoleMapper $roleMapper,
        private UserProvisioner $provisioner,
        private ?string $audience,
    ) {
    }

    public function token(Request $request): ?string
    {
        return BearerToken::fromAuthorizationHeader($request->headers->get('Authorization'));
    }

    /**
     * @throws AuthenticationException
     */
    public function authenticate(#[\SensitiveParameter] string $token): UserInterface
    {
        try {
            $identity = $this->client->verifyAccessToken($token, $this->audience);
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
