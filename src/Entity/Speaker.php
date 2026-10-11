<?php

namespace App\Entity;

use App\Encryption\EncryptedStringType;
use App\Repository\SpeakerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SpeakerRepository::class)]
class Speaker
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    #[ORM\Column(length: 100)]
    public string $name;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $company = null;

    #[ORM\Column(type: 'text')]
    public string $bio;

    /** Encrypted at rest, for the travel agency only */
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    public ?string $phone = null;

    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        public private(set) string $email,
    ) {
        $this->id = Uuid::v7();
    }
}
