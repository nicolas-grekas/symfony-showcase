<?php

namespace App\Entity\Material;

use App\Entity\Proposal;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string', length: 20)]
#[ORM\DiscriminatorMap(['slides' => Slides::class, 'video' => Video::class])]
abstract class Material
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'materials')]
    #[ORM\JoinColumn(nullable: false)]
    public Proposal $proposal;

    public function __construct(
        #[ORM\Column(length: 255)]
        public private(set) string $url,
    ) {
        $this->id = Uuid::v7();
    }
}
