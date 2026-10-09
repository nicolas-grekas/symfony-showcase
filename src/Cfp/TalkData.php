<?php

namespace App\Cfp;

use App\Enum\Format;
use App\Enum\Level;
use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
final class TalkData
{
    #[FormField]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $title = null;

    #[FormField(TextareaType::class)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 50)]
    public ?string $abstract = null;

    #[FormField(options: ['expanded' => true, 'choice_help' => 'help'])]
    #[Assert\NotNull]
    public ?Level $level = null;

    #[FormField(options: ['expanded' => true])]
    #[Assert\NotNull]
    public ?Format $format = null;
}
