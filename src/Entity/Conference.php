<?php

namespace App\Entity;

use App\Repository\ConferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ConferenceRepository::class)]
class Conference
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $id;

    public function __construct(
        #[ORM\Column(length: 100)]
        public private(set) string $name,

        #[ORM\Column(length: 100, unique: true)]
        public private(set) string $slug,

        #[ORM\Column(length: 100)]
        public private(set) string $city,

        #[ORM\Column(type: 'day_point')]
        public private(set) DatePoint $startsOn,

        #[ORM\Column(type: 'day_point')]
        public private(set) DatePoint $endsOn,

        #[ORM\Column]
        public private(set) DatePoint $cfpOpensAt,

        #[ORM\Column]
        public private(set) DatePoint $cfpClosesAt,
    ) {
        $this->id = Uuid::v7();
    }

    public function isCfpOpen(DatePoint $now): bool
    {
        return $this->cfpOpensAt <= $now && $now < $this->cfpClosesAt;
    }
}
