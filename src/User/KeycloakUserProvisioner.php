<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\User;

use Bannerstop\Keycloak\Identity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The default: stateless KeycloakUser objects, served by KeycloakUserProvider.
 */
final class KeycloakUserProvisioner implements UserProvisioner
{
    public function provision(Identity $identity, array $roles): UserInterface
    {
        return KeycloakUser::fromIdentity($identity, $roles);
    }
}
