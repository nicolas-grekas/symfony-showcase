<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
final class VideoData extends MaterialData
{
    #[FormField(IntegerType::class, ['label' => 'Length in minutes', 'required' => false])]
    #[Assert\Positive]
    public ?int $minutes = null;
}
