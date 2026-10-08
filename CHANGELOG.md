# Changelog

The project follows [Semantic Versioning](https://semver.org/). Each major
version raises the minimum PHP version and the supported Symfony versions.

## 6.3.0

- Back-channel logout: `POST /login/keycloak/backchannel-logout` verifies
  Keycloak's logout token and records the ended session; replayed tokens are
  rejected.
- Session check: the next request after a Keycloak session ended (back
  channel, or refused refresh token every `session.check_interval` seconds)
  logs the session out, including the remember-me cookie.
- The `cache` option now works with `cache.app` and other cache pools:
  their class was not recognised, so discovery and signing keys were fetched
  on every request.
  With Symfony 5.4, whose `symfony/cache` refuses to work next to
  `psr/simple-cache` 3, the cache stays off in that combination instead of
  failing.
- Requires bannerstop/keycloak 6.1.

## 6.2.0

- `login.allowed_email_domains` also takes a comma separated string, so the
  domains can come from an environment variable, e.g.
  `'%env(KEYCLOAK_ALLOWED_EMAIL_DOMAINS)%'`. An empty value allows every
  domain, like an empty list.

## 6.1.1

- Pages without a Keycloak login (the login page, form logins, public pages)
  work again when the Keycloak settings are empty, e.g. in local setups: the
  Keycloak client is now only built when a Keycloak route, a bearer token or a
  Keycloak logout needs it.
- Keycloak logins get a remember-me badge, so the firewall's `remember_me`
  applies to them like to form logins.
- After the login, users return to the page the firewall remembered (e.g. when
  a `form_login` entry point sent them to the login page), not only to
  `_target_path`. Remembered URLs of other hosts are ignored.

## 6.1.0

- Optional `directory` client (`client_id`, `client_secret`) for the user
  directory, so that the login client needs no admin API rights. Without it,
  the login client is used as before.

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
