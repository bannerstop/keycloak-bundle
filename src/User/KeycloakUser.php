<?php

declare(strict_types=1);

namespace Bannerstop\KeycloakBundle\User;

use Bannerstop\Keycloak\Identity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user that lives only in the session, for applications without their own
 * user table. The identifier is the Keycloak subject.
 */
final readonly class KeycloakUser implements UserInterface, EquatableInterface, \Stringable
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private string $subject,
        private ?string $email,
        private string $displayName,
        private array $roles,
    ) {
    }

    /**
     * @param string[] $roles
     */
    public static function fromIdentity(Identity $identity, array $roles): self
    {
        return new self($identity->getSubject(), $identity->getEmail(), $identity->getDisplayName(), $roles);
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->subject;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Required by Symfony 7.4, gone from the interface in Symfony 8.
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    #[\Override]
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->subject === $this->subject && $user->roles === $this->roles;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->displayName;
    }
}
