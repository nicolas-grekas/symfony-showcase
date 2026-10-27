<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

final readonly class EmbedProposal
{
    public function __construct(
        public Uuid $proposalId,
    ) {
    }
}
