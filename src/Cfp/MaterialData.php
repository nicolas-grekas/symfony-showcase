<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Form\Attribute\FormField;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;

// the API tells the kind of each material with a "type" key
#[DiscriminatorMap(typeProperty: 'type', mapping: ['slides' => SlidesData::class, 'video' => VideoData::class])]
#[AsFormType]
abstract class MaterialData
{
    #[FormField(UrlType::class, ['default_protocol' => 'https'])]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    public ?string $url = null;
}
