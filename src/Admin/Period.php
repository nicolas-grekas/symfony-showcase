<?php

namespace App\Admin;

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Validator\Constraints as Assert;

final class Period
{
    public function __construct(
        #[Assert\NotNull]
        public ?DatePoint $from = null,

        #[Assert\NotNull]
        public ?DatePoint $to = null,
    ) {
    }
}
