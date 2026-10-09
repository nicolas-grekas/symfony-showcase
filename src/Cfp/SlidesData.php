<?php

namespace App\Cfp;

use Symfony\Component\Form\Attribute\AsFormType;
use Symfony\Component\Serializer\Attribute\DiscriminatorMapType;

#[AsFormType]
#[DiscriminatorMapType('slides', MaterialData::class)]
final class SlidesData extends MaterialData
{
}
