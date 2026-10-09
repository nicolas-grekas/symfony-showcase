<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFormType]
abstract class MaterialData
{
    #[FormField(UrlType::class, ['default_protocol' => 'https'])]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    public ?string $url = null;
}
