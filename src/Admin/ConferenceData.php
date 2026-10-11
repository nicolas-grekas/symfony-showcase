<?php

namespace App\Admin;

use App\Entity\Conference;
use App\Form\PeriodType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
#[UniqueEntity(fields: 'slug', entityClass: Conference::class, identifierFieldNames: ['id'])]
final class ConferenceData
{
    public ?Uuid $id = null;

    #[FormField]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[FormField(options: ['help' => 'Used in the URLs of the conference.'])]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]++(?:-[a-z0-9]++)*$/', message: 'Use lowercase letters, digits and dashes.')]
    public ?string $slug = null;

    #[FormField]
    #[Assert\NotBlank]
    public ?string $city = null;

    #[FormField(PeriodType::class)]
    #[Assert\Valid]
    public Period $dates;

    #[FormField(PeriodType::class, ['type' => DateTimeType::class, 'label' => 'Call for papers'])]
    #[Assert\Valid]
    public Period $cfp;

    #[FormField(IntegerType::class, ['help' => 'No tickets are sold when it is 0.', 'required' => false, 'empty_data' => '0'])]
    #[Assert\PositiveOrZero]
    public int $ticketCapacity = 0;

    #[FormField(MoneyType::class, ['currency' => 'EUR', 'divisor' => 100])]
    #[Assert\PositiveOrZero]
    public int $ticketPrice = 0;

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
