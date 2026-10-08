<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\Security;

use Bannerstop\Keycloak\Identity;
use Bannerstop\Keycloak\Policy\EmailDomainPolicy;
use Bannerstop\Keycloak\Policy\IdentityPolicy;

/**
 * The login.allowed_email_domains option: a list, or a comma separated string
 * that usually comes from an environment variable. No domains = no restriction.
 *
 * @internal
 */
final class ConfiguredEmailDomainPolicy implements IdentityPolicy
{
    private ?EmailDomainPolicy $policy;

    /**
     * @param string|string[] $domains
     */
    public function __construct($domains, bool $requireVerified)
    {
        if (!is_string($domains) && !is_array($domains)) {
            throw new \InvalidArgumentException('The allowed e-mail domains must be a list or a comma separated string.');
        }
        $domains = array_filter(array_map('trim', is_string($domains) ? explode(',', $domains) : $domains), static fn (string $domain): bool => '' !== $domain);
        $this->policy = [] === $domains ? null : new EmailDomainPolicy(array_values($domains), $requireVerified);
    }

    public function allows(Identity $identity): bool
    {
        return null === $this->policy || $this->policy->allows($identity);
    }
}
