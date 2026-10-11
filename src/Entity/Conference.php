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

        #[ORM\Column(type: 'date_point')]
        public private(set) DatePoint $cfpOpensAt,

        #[ORM\Column(type: 'date_point')]
        public private(set) DatePoint $cfpClosesAt,

        #[ORM\Column]
        public private(set) int $ticketCapacity = 0,

        /** In cents of euro */
        #[ORM\Column]
        public private(set) int $ticketPrice = 0,
    ) {
        $this->id = Uuid::v7();
    }

    public function update(string $name, string $slug, string $city, DatePoint $startsOn, DatePoint $endsOn, DatePoint $cfpOpensAt, DatePoint $cfpClosesAt, int $ticketCapacity, int $ticketPrice): void
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->city = $city;
        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
        $this->cfpOpensAt = $cfpOpensAt;
        $this->cfpClosesAt = $cfpClosesAt;
        $this->ticketCapacity = $ticketCapacity;
        $this->ticketPrice = $ticketPrice;
    }

    public function isCfpOpen(DatePoint $now): bool
    {
        return $this->cfpOpensAt <= $now && $now < $this->cfpClosesAt;
    }

    public function isOnSale(DatePoint $now): bool
    {
        return 0 < $this->ticketCapacity && $now < $this->startsOn;
    }
}
