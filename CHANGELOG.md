# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Symfony versions.

## 1.1.0

- Optional `directory` client (`client_id`, `client_secret`) for the user
  directory, so that the login client needs no admin API rights. Without it,
  the login client is used as before.

## 1.0.0

First release, PHP 7.1.3 and later, Symfony 4.4 and 5.4.

- Login and bearer authenticators for Guard and the authenticator system
- Logout that ends the Keycloak session
- Role mapping, e-mail domain policy, user provisioning
