<?php

namespace App\Cfp;

use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;

// the API tells the kind of each material with a "type" key
#[DiscriminatorMap(typeProperty: 'type', mapping: ['slides' => SlidesData::class, 'video' => VideoData::class])]
abstract class MaterialData
{
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    public ?string $url = null;
}
