# Upgrade guide

Each major version raises the minimum PHP version and the supported Symfony
versions. Only the steps that need changes in your code are listed.

## 8.x → 9.x

- PHP 8.4 or later and Symfony 7.4 or 8 are required.
- Import `@BannerstopKeycloakBundle/Resources/config/routes.php` if you still import `routes.xml`.

## 7.x → 8.x

- PHP 8.3 or later is required. No code changes needed.

## 6.x → 7.x

- PHP 8.2 or later and Symfony 6.4 or 7 are required.
- Remove `enable_authenticator_manager` from `security.yaml`.
- Use `KeycloakUser::getUserIdentifier()` instead of `getUsername()`.
- Import `@BannerstopKeycloakBundle/Resources/config/routes.php` instead of `routes.xml` (XML routing is deprecated in Symfony 7.4).

## 5.x → 6.x

- PHP 8.1 or later and Symfony 5.4 or 6.4 are required.
- If you use `Bannerstop\Keycloak\KeycloakClient` or catch `LoginException` yourself, follow the core's upgrade guide (`LoginFailure` enum, `Algorithm` enum).

## 4.x → 5.x

- PHP 8.0 or later and Symfony 5.4 or 6 are required.
- Switch the firewalls to the authenticator system: replace `bannerstop_keycloak.guard_authenticator` with `bannerstop_keycloak.authenticator` and `bannerstop_keycloak.bearer_guard_authenticator` with `bannerstop_keycloak.bearer_authenticator` under `custom_authenticators`, and set `enable_authenticator_manager: true` on Symfony 5.4.
- Remove `success_handler: bannerstop_keycloak.logout_success_handler` from the logout configuration; the Keycloak logout now hooks into the logout event.

## 3.x → 4.x

- PHP 7.4 or later is required. No code changes needed.

## 2.x → 3.x

- PHP 7.3 or later is required. No code changes needed.

## 1.x → 2.x

- PHP 7.2 or later is required. No code changes needed.
