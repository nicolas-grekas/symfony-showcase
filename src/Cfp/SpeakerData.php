<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
final class SpeakerData
{
    #[FormField]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[FormField(options: ['required' => false])]
    #[Assert\Length(max: 100)]
    public ?string $company = null;

    #[FormField(TelType::class, ['required' => false, 'help' => 'Only given to the travel agency if your talk is accepted.'])]
    #[Assert\Regex('/^\+?[0-9 .()-]{6,20}$/', message: 'This is not a valid phone number.')]
    public ?string $phone = null;

    #[FormField(TextareaType::class, ['help' => 'A few words about you, as they will appear on the website.'])]
    #[Assert\NotBlank]
    public ?string $bio = null;
}
