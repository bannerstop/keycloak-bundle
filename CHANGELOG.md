# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Symfony versions.

## 9.1.0

- Optional `directory` client (`client_id`, `client_secret`) for the user
  directory, so that the login client needs no admin API rights. Without it,
  the login client is used as before.

## 9.0.0

- Requires PHP 8.4 or later, bannerstop/keycloak 9 and Symfony 7.4 or 8.
- Drops Symfony 6.4 and 7.0 to 7.3.
- `Resources/config/routes.xml` is gone; import `Resources/config/routes.php`.
- `KeycloakUser::eraseCredentials()` is kept for Symfony 7.4 and marked `#[\Deprecated]`.
- Tests run on PHPUnit 12.

## 8.0.0

- Requires PHP 8.3 or later and bannerstop/keycloak 8.
- Typed class constants and `#[\Override]` on every implemented framework method.

## 7.0.0

- Requires PHP 8.2 or later, bannerstop/keycloak 7 and Symfony 6.4 or 7.
- Drops Symfony 5.4: `KeycloakUserProvider::loadUserByUsername()` and `KeycloakUser::getUsername()`, `getPassword()` and `getSalt()` are gone.
- Login errors are stored under `SecurityRequestAttributes::AUTHENTICATION_ERROR`.
- The routes ship as `Resources/config/routes.php`; `routes.xml` is kept for now, because Symfony 7.4 deprecates XML routing.
- Readonly classes; bearer tokens are marked `#[\SensitiveParameter]`.

## 6.0.0

- Requires PHP 8.1 or later, bannerstop/keycloak 6 and Symfony 5.4 or 6.4.
- Login error keys come from the core's `LoginFailure` enum; the keys themselves (`keycloak.login.*`) are unchanged.
- Readonly properties; `LoginController::callback()` returns `never`.

## 5.0.0

- Requires PHP 8.0 or later, bannerstop/keycloak 5 and Symfony 5.4 or 6.
- Drops Symfony 4.4 and Guard: the Guard authenticators and the logout success handler are gone.
- Constructor property promotion throughout.

## 4.0.0

- Requires PHP 7.4 or later and bannerstop/keycloak 4.
- Typed properties and arrow functions throughout.

## 3.0.0

- Requires PHP 7.3 or later and bannerstop/keycloak 3.

## 2.0.0

- Requires PHP 7.2 or later and bannerstop/keycloak 2.

## 1.0.0

First release, PHP 7.1.3 and later, Symfony 4.4 and 5.4.

- Login and bearer authenticators for Guard and the authenticator system
- Logout that ends the Keycloak session
- Role mapping, e-mail domain policy, user provisioning
