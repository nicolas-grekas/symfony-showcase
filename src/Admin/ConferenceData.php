<?php

namespace App\Admin;

use App\Entity\Conference;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: 'slug', entityClass: Conference::class, identifierFieldNames: ['id'])]
final class ConferenceData
{
    public ?Uuid $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]++(?:-[a-z0-9]++)*$/', message: 'Use lowercase letters, digits and dashes.')]
    public ?string $slug = null;

    #[Assert\NotBlank]
    public ?string $city = null;

    #[Assert\Valid]
    public Period $dates;

    #[Assert\Valid]
    public Period $cfp;

    #[Assert\PositiveOrZero]
    public int $ticketCapacity = 0;

    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    public ?int $ticketPrice = 0;

    public function __construct()
    {
        $this->dates = new Period();
        $this->cfp = new Period();
    }

    public static function fromConference(Conference $conference): self
    {
        $data = new self();
        $data->id = $conference->id;
        $data->name = $conference->name;
        $data->slug = $conference->slug;
        $data->city = $conference->city;
        $data->dates = new Period($conference->startsOn, $conference->endsOn);
        $data->cfp = new Period($conference->cfpOpensAt, $conference->cfpClosesAt);
        $data->ticketCapacity = $conference->ticketCapacity;
        $data->ticketPrice = $conference->ticketPrice;

        return $data;
    }
}
