<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    /** @var list<string> */
    #[ORM\Column]
    public array $roles = [];

    #[ORM\Column(nullable: true)]
    public ?string $password = null;

    #[ORM\Column]
    public bool $enabled = true;

    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        public private(set) string $email,

        #[ORM\Column(length: 100)]
        public string $name,
    ) {
        $this->id = Uuid::v7();
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function __serialize(): array
    {
        $data = (array) $this;

        // the session keeps a hash of the password only, enough to detect when it changes
        if (null !== $this->password) {
            $data['password'] = hash('crc32c', $this->password);
        }

        return $data;
    }
}
