<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class ProposalDecided implements DomainEvent
{
    public function __construct(
        public Uuid $proposalId,
        public bool $accepted,
    ) {
    }
}
