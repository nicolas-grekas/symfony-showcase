<?php

namespace App\Cfp;

use Symfony\Component\Validator\Constraints as Assert;

final class SpeakerData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\Length(max: 100)]
    public ?string $company = null;

    #[Assert\Regex('/^\+?[0-9 .()-]{6,20}$/', message: 'This is not a valid phone number.')]
    public ?string $phone = null;

    #[Assert\NotBlank]
    public ?string $bio = null;
}
