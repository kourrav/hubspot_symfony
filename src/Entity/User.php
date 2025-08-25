<?php
// src/Entity/User.php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"integer")]
    private $id;

    #[ORM\Column(type:"string", length:180, unique:true)]
    #[Assert\NotBlank]
    private $email;

    #[ORM\Column(type:"string")]
    private $password;

    #[ORM\Column(type:"string", length:50)]
    private $firstName;

    #[ORM\Column(type:"string", length:50)]
    private $lastName;

    #[ORM\Column(type:"string", nullable:true)]
    private $profilePicture;

    #[ORM\Column(type:"json")]
    private $roles = [];

    public function getId(): ?int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    // NEW: Required by UserInterface
    public function getUserIdentifier(): string { return $this->email; }

    // Legacy: implement getUsername() for backward compatibility
    public function getUsername(): string { return $this->getUserIdentifier(); }

    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getProfilePicture(): ?string { return $this->profilePicture; }
    public function setProfilePicture(?string $profilePicture): self { $this->profilePicture = $profilePicture; return $this; }

    // Required by UserInterface
    public function getSalt(): ?string { return null; }

    public function eraseCredentials() {}
}
