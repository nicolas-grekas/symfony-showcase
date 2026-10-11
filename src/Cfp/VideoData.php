<?php

namespace App\Cfp;

use Symfony\Component\Validator\Constraints as Assert;

final class VideoData extends MaterialData
{
    #[Assert\Positive]
    public ?int $minutes = null;
}
