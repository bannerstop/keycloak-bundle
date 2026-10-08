# Upgrade guide

Each major version raises the minimum PHP version and the supported Symfony
versions. Only the steps that need changes in your code are listed.

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
