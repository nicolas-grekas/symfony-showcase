<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Serializer\Attribute\DiscriminatorMapType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
#[DiscriminatorMapType('video', MaterialData::class)]
final class VideoData extends MaterialData
{
    #[FormField(options: ['label' => 'Length in minutes'])]
    #[Assert\Positive]
    public ?int $minutes = null;
}
