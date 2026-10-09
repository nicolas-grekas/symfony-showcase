<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
final class SpeakerData
{
    #[FormField]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[FormField(EmailType::class)]
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[FormField(options: ['required' => false])]
    #[Assert\Length(max: 100)]
    public ?string $company = null;

    #[FormField(TextareaType::class, ['help' => 'A few words about you, as they will appear on the website.'])]
    #[Assert\NotBlank]
    public ?string $bio = null;
}
