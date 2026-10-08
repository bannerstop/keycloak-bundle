<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Tests\App;

use Bannerstop\KeycloakBundle\User\KeycloakUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class TestController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly AuthenticationUtils $authenticationUtils,
    ) {
    }

    public function me(): JsonResponse
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof KeycloakUser) {
            return new JsonResponse(['user' => null]);
        }

        return new JsonResponse(['subject' => $user->getSubject(), 'email' => $user->getEmail(), 'name' => $user->getDisplayName(), 'roles' => $user->getRoles()]);
    }

    public function error(): JsonResponse
    {
        $error = $this->authenticationUtils->getLastAuthenticationError();

        return new JsonResponse(['error' => $error?->getMessageKey()]);
    }
}
