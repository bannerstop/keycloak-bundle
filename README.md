# bannerstop/keycloak-bundle

Symfony integration of [bannerstop/keycloak](https://github.com/bannerstop/keycloak):
single sign-on with Keycloak for the Symfony security component.

- Browser login (authorization code flow with PKCE) as an authenticator, including the entry point
- Bearer tokens for stateless API firewalls
- Logout that also ends the Keycloak session
- Role mapping from realm roles, client roles and groups
- Works without a user table (stateless `KeycloakUser`) or with your own users (`UserProvisioner`)

## Versions

| Version | PHP     | Symfony                                      |
|---------|---------|----------------------------------------------|
| 1.x     | ≥ 7.1.3 | 4.4 (Guard), 5.4 (Guard or authenticator system) |
| 2.x     | ≥ 7.2   | 4.4 (Guard), 5.4 (Guard or authenticator system) |
| 3.x     | ≥ 7.3   | 4.4 (Guard), 5.4 (Guard or authenticator system) |
| 4.x     | ≥ 7.4   | 4.4 (Guard), 5.4 (Guard or authenticator system) |
| 5.x     | ≥ 8.0   | 5.4, 6.x (authenticator system) |
| 6.x     | ≥ 8.1   | 5.4, 6.4 |
| 7.x     | ≥ 8.2   | 6.4, 7.x |
| 8.x     | ≥ 8.3   | 6.4, 7.x |
| 9.x     | ≥ 8.4   | 7.4, 8.x |

## Installation

```bash
composer require bannerstop/keycloak-bundle symfony/http-client nyholm/psr7
```

Without Symfony Flex, register the bundle in `config/bundles.php`:

```php
Bannerstop\KeycloakBundle\BannerstopKeycloakBundle::class => ['all' => true],
```

Import the routes (`/login/keycloak` and `/login/keycloak/callback`), e.g. in
`config/routes/bannerstop_keycloak.yaml`:

```yaml
bannerstop_keycloak:
    resource: '@BannerstopKeycloakBundle/Resources/config/routes.php'
```

In Keycloak, register `https://your-app.example/login/keycloak/callback` as
redirect URI and your logout target as post logout redirect URI. See the
[core README](https://github.com/bannerstop/keycloak#keycloak-setup) for the
full client setup.

## Configuration

```yaml
# config/packages/bannerstop_keycloak.yaml
bannerstop_keycloak:
    server_url: 'https://sso.example.com'
    realm: 'example'
    client_id: 'my-app'
    client_secret: '%env(KEYCLOAK_CLIENT_SECRET)%'

    login:
        allowed_email_domains: ['example.com'] # empty: everybody in the realm
        default_target_path: '/'
        failure_path: 'app_login'   # route or path; shows the error via AuthenticationUtils
        logout_target: '/'

    roles:
        default_roles: ['ROLE_USER']
        realm_roles:
            admin: ROLE_ADMIN
        client_roles:
            my-app:
                editor: [ROLE_EDITOR]
        groups:
            /staff/it: ROLE_IT

    bearer:
        audience: 'my-api'          # defaults to the client id

    # user_provisioner: App\Security\KeycloakUserProvisioner
```

`cache` (default `cache.app`) caches the discovery document and the signing
keys. `http_client`, `request_factory` and `stream_factory` take service ids
if you do not use `symfony/http-client`.

## Security

```yaml
security:
    providers:
        keycloak:
            id: bannerstop_keycloak.user_provider
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            provider: keycloak
            custom_authenticators: [bannerstop_keycloak.bearer_authenticator]
        main:
            lazy: true
            provider: keycloak
            custom_authenticators: [bannerstop_keycloak.authenticator]
            logout:
                path: app_logout
    access_control:
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: ROLE_USER }
```

### Your own users

By default users only live in the session (`KeycloakUser`, identified by the
Keycloak subject) and are served by `bannerstop_keycloak.user_provider`.

To use your own user entity instead, implement
`Bannerstop\KeycloakBundle\User\UserProvisioner`. After every successful login
and every accepted bearer token, the bundle calls `provision()` with the
verified identity and the mapped roles; the user it returns is the
authenticated user. Link accounts by the Keycloak subject, not by e-mail
address, which can change:

```php
// src/Security/KeycloakUserProvisioner.php
namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Bannerstop\Keycloak\Identity;
use Bannerstop\KeycloakBundle\User\UserProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class KeycloakUserProvisioner implements UserProvisioner
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Identity $identity, array $roles): UserInterface
    {
        $user = $this->users->findOneBy(['keycloakId' => $identity->getSubject()])
            ?? (new User())->setKeycloakId($identity->getSubject());
        $user->setEmail($identity->getEmail())
            ->setName($identity->getDisplayName())
            ->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
```

`user_provisioner` is an option of this bundle (not of Symfony itself) and
takes the service id of your provisioner. With autowiring, the id is the
class name:

```yaml
# config/packages/bannerstop_keycloak.yaml
bannerstop_keycloak:
    # ...
    user_provisioner: App\Security\KeycloakUserProvisioner
```

The firewall then uses your usual entity provider instead of
`bannerstop_keycloak.user_provider`:

```yaml
# config/packages/security.yaml
security:
    providers:
        app_users:
            entity:
                class: App\Entity\User
                property: keycloakId
    firewalls:
        main:
            provider: app_users
            custom_authenticators: [bannerstop_keycloak.authenticator]
```

The provisioner hands Symfony the user at login; on every following request
Symfony reloads it through the firewall's provider. That provider must
therefore return the same entity class.

### Login errors

A failed login redirects to `failure_path`. `AuthenticationUtils::getLastAuthenticationError()`
then returns an exception whose message key is one of
`keycloak.login.state_mismatch`, `.cancelled`, `.provider_error`,
`.invalid_token` or `.not_allowed`. Translate them in the `security` domain.

### Services

| Service | Use |
|---------|-----|
| `Bannerstop\Keycloak\KeycloakClient` | refresh tokens, userinfo, verify tokens yourself |
| `Bannerstop\Keycloak\Admin\UserDirectory` | list users of the realm (service account with `view-users`) |
| `Bannerstop\Keycloak\Role\RoleMapper` | the configured role mapping |

## License

MIT, see [LICENSE](LICENSE). Security issues: see the
[core package's security policy](https://github.com/bannerstop/keycloak/blob/main/SECURITY.md).
