<?php

namespace App\Entity;

use App\Repository\UsersRepo;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UsersRepo::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken.')]
class UsersEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'user_id', type: 'integer')]
    private ?int $user_id = null;

    #[ORM\Column(name: 'username', type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Please choose a username.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Your username should be at least {{ limit }} characters long.',
        maxMessage: 'Your username cannot be longer than {{ limit }} characters.',
    )]
    private ?string $username = null;

    #[ORM\Column(name: 'password', type: 'string')]
    private ?string $password = null;

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function eraseCredentials(): void
    {
        // No temporary, plaintext credentials are stored on this entity.
    }
}
