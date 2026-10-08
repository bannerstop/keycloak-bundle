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
final class KeycloakUser implements UserInterface, EquatableInterface
{
    private string $subject;
    private ?string $email;
    private string $displayName;

    /** @var string[] */
    private array $roles;

    /**
     * @param string[] $roles
     */
    public function __construct(string $subject, ?string $email, string $displayName, array $roles)
    {
        $this->subject = $subject;
        $this->email = $email;
        $this->displayName = $displayName;
        $this->roles = $roles;
    }

    /**
     * @param string[] $roles
     */
    public static function fromIdentity(Identity $identity, array $roles): self
    {
        return new self($identity->getSubject(), $identity->getEmail(), $identity->getDisplayName(), $roles);
    }

    public function getUserIdentifier(): string
    {
        return $this->subject;
    }

    public function getUsername(): string
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
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->subject === $this->subject && $user->roles === $this->roles;
    }

    public function __toString(): string
    {
        return $this->displayName;
    }
}
