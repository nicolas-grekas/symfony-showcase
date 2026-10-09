<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class SendTravelDetails implements TravelCommand
{
    public function __construct(
        public Uuid $proposalId,
    ) {
    }
}
