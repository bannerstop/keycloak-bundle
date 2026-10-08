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
    private readonly ?EmailDomainPolicy $policy;

    /**
     * @param string|list<string> $domains
     */
    public function __construct(string|array $domains, bool $requireVerified)
    {
        $domains = array_filter(
            array_map(trim(...), is_string($domains) ? explode(',', $domains) : $domains),
            static fn (string $domain): bool => '' !== $domain,
        );
        $this->policy = [] === $domains ? null : new EmailDomainPolicy(array_values($domains), $requireVerified);
    }

    public function allows(Identity $identity): bool
    {
        return $this->policy?->allows($identity) ?? true;
    }
}
