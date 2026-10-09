<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class ProposalSubmitted implements DomainEvent
{
    public function __construct(
        public Uuid $proposalId,
    ) {
    }
}
