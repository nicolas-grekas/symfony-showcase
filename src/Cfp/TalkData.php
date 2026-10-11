<?php

namespace App\Cfp;

use App\Enum\Format;
use App\Enum\Level;
use Symfony\Component\Validator\Constraints as Assert;

final class TalkData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $title = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 50)]
    public ?string $abstract = null;

    #[Assert\NotNull]
    public ?Level $level = null;

    #[Assert\NotNull]
    public ?Format $format = null;
}
