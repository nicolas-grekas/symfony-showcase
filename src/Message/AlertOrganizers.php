<?php

namespace App\Message;

final readonly class AlertOrganizers
{
    public function __construct(
        public string $subject,
        public string $details,
    ) {
    }
}
