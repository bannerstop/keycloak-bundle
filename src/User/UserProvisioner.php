<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\User;

use Bannerstop\Keycloak\Identity;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Turns a verified Keycloak identity into the application's user, e.g. by
 * finding or creating an entity. Link accounts by $identity->getSubject().
 *
 * The returned user must be loadable by the user provider of the firewall,
 * because Symfony refreshes it from there on every following request.
 */
interface UserProvisioner
{
    /**
     * @param string[] $roles The roles the RoleMapper derived from the identity
     */
    public function provision(Identity $identity, array $roles): UserInterface;
}
